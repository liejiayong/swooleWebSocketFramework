<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/10/30
 * Time: 15:19
 */

namespace Library;

use Library\Entity\MessageQueue\EntityRabbit;
use Library\Entity\Swoole\EntitySwooleWebSocketSever;
use Library\Virtual\Handler\AbstractHandler;
use Library\Virtual\Object\AbstractMessageObject;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPSwooleConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Class Message
 * @package Library
 */
class Message
{
    /**
     * 发送消息到消息队列
     * @param AbstractMessageObject $messageObject
     */
    public static function publish(AbstractMessageObject $messageObject)
    {
        $connection = EntityRabbit::getInstance();

        $channel = $connection->channel();

        $queue = $messageObject->channel . "_exchange_" . Config::get('app.server_id');

        $channel->queue_declare($queue, false, true, false, false);

        $exchangeName = Config::get('app.is_server') ? Config::get('rabbit.server.message_exchange') : Config::get('rabbit.local.message_exchange');

        $channel->exchange_declare($exchangeName, 'direct', false, true, false);

        $channel->queue_bind($queue, $exchangeName, $queue);

        $message = new AMQPMessage(
            serialize($messageObject),
            [
                'content_type' => 'text/plain',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
            ]
        );
        $channel->basic_publish($message, $exchangeName, $queue);

        $channel->close();
    }

    /**
     * 消化消息队列的消息
     */
    public static function consume()
    {
        $channelList = Channel::getChannelList();
        foreach ($channelList as $key => $channel) {
            go(function () use ($channel) {
                $queue = (string)$channel . "_exchange_" . Config::get('app.server_id');
                $consumerTag = 'consumer';
                $rabbitConfig = Config::get('app.is_server') ? Config::get('rabbit.server') : Config::get('rabbit.local');
                $connection = new AMQPSwooleConnection($rabbitConfig['host'],
                    $rabbitConfig['port'],
                    $rabbitConfig['user'],
                    $rabbitConfig['password'],
                    $rabbitConfig['vhost']
                );

                $exchangeName = Config::get('app.is_server') ? Config::get('rabbit.server.message_exchange') : Config::get('rabbit.local.message_exchange');

                $channel = $connection->channel();

                $channel->queue_declare($queue, false, true, false, false);

                $channel->exchange_declare($exchangeName, 'direct', false, true, false);

                $channel->queue_bind($queue, $exchangeName);

                /**
                 * @param \PhpAmqpLib\Message\AMQPMessage $message
                 */
                $callback = function ($message) {

                    /* @var AMQPChannel $channel */
                    $channel = $message->delivery_info['channel'];

                    $channel->basic_ack($message->delivery_info['delivery_tag']);

                    /* @var AbstractMessageObject $messageBody */
                    $messageBody = unserialize($message->body);

                    $channelObject = Channel::route(['channel' => $messageBody->channel]);

                    $handlerClass = $channelObject->getHandler();

                    /* @var AbstractHandler $handler */
                    $handler = new $handlerClass();

                    //fd存在则触发发送函数
                    if (EntitySwooleWebSocketSever::getInstance()->exist((int)($messageBody->toFd))) {
                        $handler->consume($messageBody);
                    }
                };

                $channel->basic_consume($queue, $consumerTag, false, false, false, false, $callback);

                while (count($channel->callbacks)) {
                    $channel->wait();
                }
            });
        }
    }
}