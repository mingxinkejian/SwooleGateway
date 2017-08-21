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

namespace SwooleGateway\Server\Socket;

use SwooleGateway\Server\BaseServer;
use SwooleGateway\Server\Protocols\BinaryProtocol;
use SwooleGateway\Server\Connection\TCPConnection;
/**
* 
*/
class SocketServer extends BaseServer
{

    public $onReceives = array();
    //默认最大包长度
    const MAX_PACK_LEN = 0x200000; //2M大小
    const PACK_HEAD_LEN = 4;

    public function __construct($config,$mode = SWOOLE_BASE)
    {
        $this->_settings = $config;
        $url = $this->parseUrl($config['uri']);
        $this->_swType = $url->type;
        $this->swServer = new \swoole_server($url->host, $url->port, $mode, $url->type);
        parent::__construct();
    }

    public function sendToSocket($fd,$data)
    {
        $dataLength = strlen($data);

        $headerLength = pack("N", $dataLength);

        if($dataLength <= self::MAX_PACK_LEN - self::PACK_HEAD_LEN)
        {
            return $this->send($fd, $headerLength . $data);    
        }
        else
        {
            $this->send($fd, $headerLength);

            for ($i=0; $i < $dataLength; $i += self::MAX_PACK_LEN)
            { 
                if(!$this->send($fd, substr($data, $i,min($dataLength - $i, self::MAX_PACK_LEN) )))
                {
                    return false;
                }
            }

            return true;
        }
    }

    private function send($fd,$data)
    {
        if($this->swServer->exist($fd))
        {
            return $this->swServer->send($fd, $data);
        }
        return false;
    }

    private function parseUrl($uri)
    {
        $this->_defaultScheme = $uri;

        $parseResult = new \stdClass();
        $scheme = parse_url($uri);
        if($scheme)
        {
            switch (strtolower($scheme['scheme']))
            {
                case 'tcp':
                case 'tcp4':
                    $parseResult->type = SWOOLE_SOCK_TCP;
                    $parseResult->host = $scheme['host'];
                    $parseResult->port = $scheme['port'];
                    break;
                case 'tcp6':
                    $parseResult->type = SWOOLE_SOCK_TCP6;
                    $parseResult->host = $scheme['host'];
                    $parseResult->port = $scheme['port'];
                    break;
                case 'ssl':
                case 'sslv2':
                case 'sslv3':
                case 'tls':
                    $parseResult->type = SWOOLE_SOCK_TCP | SWOOLE_SSL;
                    $parseResult->host = $scheme['host'];
                    $parseResult->port = $scheme['port'];
                    break;
                case 'unix':
                    $parseResult->type = SWOOLE_UNIX_STREAM;
                    $parseResult->host = $scheme['path'];
                    $parseResult->port = 0;
                    break;
                default:
                    throw new Exception("Can't support this scheme: {$p['scheme']}");
            }
        }
        $this->_defaultHost = $parseResult->host;
        $this->_defaultPort = $parseResult->port;
        
        return $parseResult;
    }

    public function startServer()
    {
        if($this->_swType != SWOOLE_UNIX_STREAM)
        {
            $this->_swSettings['open_tcp_nodelay'] = $this->noDelay;
        }
        //socket服务器使用固定方式的包头包体
        $this->_swSettings['svrConf']['open_eof_check'] = false;
        $this->_swSettings['svrConf']['open_length_check'] = false;
        $this->_swSettings['svrConf']['open_eof_split'] = false;

        $this->swServer->set($this->_swSettings['svrConf']);
        $this->socketHandle($this->swServer);
        parent::startServer();
    }

    // public function getOnReceive()
    // {
    //     $bytes = '';
    //     $headerLength = self::PACK_HEAD_LEN;
    //     $dataLength = -1;

    //     return function($server,$fd,$fromId,$data) use (&$bytes, &$headerLength, &$dataLength){
    //         $bytes .= $data;
    //         while(true)
    //         {
    //             $length = strlen($bytes);
    //             //拆包 len+body
    //             if($dataLength < 0 && $length >= $headerLength)
    //             {
    //                 $unpackArr = unpack("NdataLen", substr($bytes, 0, self::PACK_HEAD_LEN));
    //                 $dataLength = $unpackArr['dataLen'];
    //             }

    //             if(($dataLength >= 0) && ($length - $headerLength) >= $dataLength)
    //             {
    //                 $context = new \stdClass();
    //                 $context->server = $server;
    //                 $context->socket = $fd;
    //                 $context->fd = $fd;
    //                 $context->fromId = $fromId;
    //                 $context->userData = new \stdClass();

    //                 $data = substr($bytes, $headerLength, $dataLength);
    //                 $bytes = substr($bytes, $headerLength + $dataLength);

    //                 $context->userData->pkg = $data;

    //                 $headerLength = self::PACK_HEAD_LEN;
    //                 $dataLength = -1;
    //                 try {
    //                     if(is_callable($this->onReceivePkg))
    //                     {
    //                         call_user_func($this->onReceivePkg, $context);
    //                     }
    //                 }
    //                 catch (Exception $e) { $server->close($fd); }
    //                 catch (Throwable $e) { $server->close($fd); }
    //             }
    //             else
    //             {
    //                 break;
    //             }
                
    //         }
    //     };
    // }

    public function socketHandle($server)
    {
        $server->on('connect', array($this, 'onConnect'));
        $server->on('close', array($this, 'onClose'));
        $server->on("receive", array($this, 'onReceive'));
        $server->on("start", array($this, 'onStart'));
        $server->on('workerstart',array($this, 'onWorkerStart'));
    }

    public function onStart($server)
    {
        parent::onStart($server);
        if(is_callable($this->onStart))
        {
            call_user_func($this->onStart, $server);
        }
    }

    public function onWorkerStart($server,$workerId)
    {
        parent::onWorkerStart($server, $workerId);
        if(is_callable($this->onWorkerStart))
        {
            call_user_func($this->onWorkerStart, $server, $workerId);
        }
    }
    /***********  此处的context需要重构  ************************/
    public function onConnect($server,$fd,$fromId)
    {
        $connection = new TCPConnection();
        $connection->protocol = new BinaryProtocol($this, $fromId, $fd);
        $connection->protocol->onReceivePkg = $this->onReceivePkg;
        $connection->server = $server;
        $connection->socket = $fd;
        $connection->fd = $fd;
        $connection->fromId = $fromId;
        $connection->userData = new \stdClass();
        $this->onReceives[$fd] = $connection;//$this->getOnReceive();

        try {

            if(is_callable($this->onAccept))
            {
                call_user_func($this->onAccept, $connection);
            }
        }
        catch (Exception $e) { $server->close($fd); unset($this->onReceives[$fd]); }
        catch (Throwable $e) { $server->close($fd); unset($this->onReceives[$fd]); }
    }

    public function onClose($server,$fd,$fromId)
    {
        $connection = $this->onReceives[$fd];
        unset($this->onReceives[$fd]);
        try {
            if (is_callable($this->onClose)) {
                call_user_func($this->onClose, $connection);
            }
        }
        catch (Exception $e) {}
        catch (Throwable $e) {}
    }

    public function onReceive($server,$fd,$fromId,$data)
    {
        if(isset($this->onReceives[$fd]))
        {
            // $onReceive = $this->onReceives[$fd];
            // $onReceive($server, $fd, $fromId, $data);
            $connection = $this->onReceives[$fd];
            $connection->protocol->fd = $fd;
            $connection->protocol->fromId = $fromId;
            $connection->protocol->decode($connection, $data);
        }
        else
        {
            $server->close($fd, true);
        }
    }
}