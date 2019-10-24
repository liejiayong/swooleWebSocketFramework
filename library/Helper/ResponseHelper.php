<?php

namespace Library\Helper;

use Library\Entity\Swoole\EntitySwooleWebSever;
use Swoole\Coroutine;

class ResponseHelper
{
    /**
     * @var array $instancePool
     */
    private static $instancePool = [];

    private function __construct()
    {

    }

    private function __clone()
    {

    }

    /**
     * 获取整个请求对象
     * @return array
     */
    public static function getInstance()
    {
        return self::$instancePool;
    }

    /**
     * 回收指定协程内的对象
     * @param int $workerId
     */
    public static function recoverInstance(int $workerId = -1)
    {
        if ($workerId == -1) {
            $cid = Coroutine::getuid();
            $workerId = EntitySwooleWebSever::getInstance()->worker_id;
            unset(static::$instancePool[$workerId][$cid]);
        } else {
            unset(static::$instancePool[$workerId]);
        }
    }

    /**
     * json格式的success
     * @param array $jsonData
     * @param int $options
     */
    public static function json(array $jsonData = [], int $options = (JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK))
    {
        $cid = Coroutine::getuid();
        $workId = EntitySwooleWebSever::getInstance()->worker_id;
        static::$instancePool[$workId][$cid] = json_encode($jsonData, $options);
    }

    /**
     * 获取当前协程的返回数据
     */
    public static function response()
    {
        $cid = Coroutine::getuid();
        $workerId = EntitySwooleWebSever::getInstance()->worker_id;
        return static::$instancePool[$workerId][$cid] ?? '';
    }
}