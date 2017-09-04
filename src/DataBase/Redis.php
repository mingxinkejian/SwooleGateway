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

use SwooleGateway\Logger\ServerException;

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

        try
        {
            $connectResult = $this->_redis->pconnect($this->_config['host'],$this->_config['port']);
            if(!empty($this->_config['password']) && $connectResult)
            {
                $this->_redis->auth($this->_config['password']);
            }
        }
        catch(\Exception $e)
        {
            ServerException::appException($e);
        }
    }

    public function ping()
    {
        try 
        {
            if($this->_redis->ping() !== '+PONG')
            {
                $this->connect();
            }
        }
        catch(\Exception $e)
        {
            ServerException::appException($e);
        }
    }

    public function __call($method,$args)
    {
        try
        {
            if(method_exists($this->_redis, $method))
            {
                return call_user_func_array(array($this->_redis, $method), $args);
            }
        }
        catch(\Exception $e)
        {
            ServerException::appException($e);
        }
    }

    public function __destruct()
    {
        $this->_redis->close();
    }
}