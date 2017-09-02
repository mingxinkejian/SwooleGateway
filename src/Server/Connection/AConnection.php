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
namespace SwooleGateway\Server\Connection;

/**
 * 连接信息
 */
abstract class AConnection
{
    //默认最大包长度
    const MAX_PACK_LEN = 0x200000; //2M大小
    const PACK_HEAD_LEN = 4;

    public $fd;     //因为在底层可以保证fd的唯一性，此处fd即可做唯一标识即可

    public $server;
    public $socket;
    public $fromId;
    public $userData;

    public $protocol;
    
    public $key;
    public $groups;
    public $session;

    function __construct()
    {
        
    }

    public function getConnectionInfo($server = null,$fd = -1)
    {
        //获取客户端连接信息
        if ($server == null) {
            $server = $this->server;
        }
        
        if($fd == -1)
        {
            $fd = $this->fd;
        }
        $swConnInfo = $server->connection_info($fd);
        return $swConnInfo;
    }

    public function close()
    {
        $this->server->close($this->fd);
    }

    public function send($data)
    {
        $dataBuff = $this->protocol->encode($data);
        $dataLength = strlen($dataBuff);

        $headerLength = pack("N", $dataLength);

        if($dataLength <= self::MAX_PACK_LEN - self::PACK_HEAD_LEN)
        {
            return $this->sendToClient($this->fd, $headerLength . $dataBuff);    
        }
        else
        {
            $this->sendToClient($this->fd, $headerLength);

            for ($i=0; $i < $dataLength; $i += self::MAX_PACK_LEN)
            { 
                if(!$this->sendToClient($this->fd, substr($dataBuff, $i,min($dataLength - $i, self::MAX_PACK_LEN) )))
                {
                    return false;
                }
            }

            return true;
        }
    }

    private function sendToClient($fd,$data)
    {
        if($fd != -1 && $this->server->exist($fd))
        {
            return $this->server->send($fd, $data);
        }
        else if ($fd == -1 && get_class($this->server) === 'swoole_client')
        {
            return $this->server->send($data);
        }else
        {
            echo __FILE__ . PHP_EOL;
        }
        return false;
    }
}