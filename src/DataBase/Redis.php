<?php
#
# This file is part of SwooleGateway.
#
# Licensed under The MIT License
# For full copyright and license information, please see the MIT-LICENSE.txt
# Redistributions of files must retain the above copyright notice.
#
# @author    mingming<363658434@qq.com>
# @copyright mingming<363658434@qq.com>
# @link      xxxx
# @license   http://www.opensource.org/licenses/mit-license.php MIT License
#

namespace SwooleGateway\DataBase;

/**
* Redis的操作放弃
* 
*/
class Redis
{
    private $_redis;
    private $_config;

    public function __construct($redisConf)
    {
        $this->_config = $redisConf;
        $this->_redis = new \Redis();
        $this->connect();
    }

    private function connect()
    {
        $this->_redis->pconnect($redisConf['host'],$redisConf['port']);
        if(!empty($redisConf['password']))
        {
            $this->_redis->auth($redisConf['password']);
        }
    }

    public function ping()
    {
        if($this->_redis->ping())
        {
            $this->connect();
        }
    }

    public function __call($method,$args)
    {
        if(method_exists($this->_redis, $method))
        {
            return call_user_func_array(array($this->_redis, $method), $args);
        }
    }

    public function __destruct()
    {
        $this->_redis->close();
    }
}