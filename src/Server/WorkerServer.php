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

namespace SwooleGateway\Server;

use SwooleGateway\IO\FileIO;
use SwooleGateway\Common\CmdDefine;
use SwooleGateway\Server\Protocols\GatewayWorkerProtocol;
use SwooleGateway\Server\Protocols\BinaryProtocol;
use SwooleGateway\Server\Connection\TCPConnection;
use SwooleGateway\Logger\LoggerLevel;
/**
* 
*/
class WorkerServer extends GatewayObject
{
    /**
     * 注册中心信息
     * @var [type]
     */
    private $_registerConnClient;
    private $_registerConnection;

    private $_pingRegisterTimerId;

    private $_pingWorkerTimerId;

    private $_tryToConnectGatewayTimerId = -1;
    /**
     * 保存网关信息
     * @var array
     */
    private $_gatewayAddresses = array();
    private $_gatewayConnection;

    public function __construct($config,$mode = SWOOLE_BASE)
    {
        $this->initServer($config, $mode);
    }

    private function initServer($config,$mode)
    {
        $this->_settings = $config;
        $this->parseConfig($config,$mode);

        $this->_server->onStart = array($this, 'onStart');
        $this->_server->onWorkerStart = array($this, 'onWorkerStart');
        $this->_server->onAccept = array($this, 'onAccept');
        $this->_server->onClose = array($this, 'onClose');
        $this->_server->onReceivePkg = array($this, 'onReceivePkg');
    }

    /**
     * 服务器启动回调
     * 启动后，做服务发现，将本服务器向注册中心注册
     * @param  [type] $server [description]
     * @return [type]         [description]
     */
    public function onStart($server)
    {

    }

    public function onWorkerStart($server,$workerId)
    {
        // 注册gateway的内部通讯地址到register，worker 去连这个地址，以便 gateway 与 worker 之间建立起 TCP 长连接
        $this->connectRegisterAddress($server,$workerId);
    }

    /**
     * 收到完整Client连接
     * @param  [type] $connection [description]
     * @return [type]          [description]
     */
    public function onAccept($connection)
    {

    }
    /**
     * 收到客户端关闭信息
     * @param  [type] $connection [description]
     * @return [type]          [description]
     */
    public function onClose($connection)
    {

    }

    /**
     *  收到一个完整Client数据包
     *  $context = new \stdClass();
     *  $context->server = $server;
     *  $context->socket = $fd;
     *  $context->fd = $fd;
     *  $context->fromId = $fromId;
     *  $context->userData = new \stdClass();
     *  $context->userData->pkg = $data;
     * @param  [type] $connection [description]
     * @param  [type] $context [description]
     * @return [type]          [description]
     */
    public function onReceivePkg($connection,$context)
    {

    }

    /**
     * 生成connection id
     * @return int
     */
    protected function generateConnectionId()
    {
        $maxUnsignedInt = 4294967295;
        if(self::$_connectionIdRecorder >= $maxUnsignedInt)
        {
            self::$_connectionIdRecorder = 0;
        }
        while(++self::$_connectionIdRecorder <= $maxUnsignedInt)
        {
            if(!isset($this->_clientConnections[self::$_connectionIdRecorder]))
            {
                break;
            }
        }
        return self::$_connectionIdRecorder;
    }

    /*****************************************注册中心相关*********************************************************/
    private function connectRegisterAddress($server,$workerId)
    {
        $registData['cmd'] = CmdDefine::CMD_REGISTER_REQ_AND_RESP;
        $registData['key'] = $this->_settings['clusterMakerKey'];
        $registData['md5'] = md5($registData['cmd'] . $registData['key']);

        $scheme = parse_url($this->_settings['broadcastUri']);

        $client = new \swoole_client(SWOOLE_SOCK_UDP, SWOOLE_SOCK_SYNC);
        $client->connect($scheme['host'], $scheme['port']);
        $client->send(json_encode($registData));

        $recvData = $client->recv();
        if(is_bool($recvData) && $recvData == false)
        {
            echo "注册中心连接失败！" . PHP_EOL;
            $this->closeServer();
        }
        else
        {
            $parseRecvData = json_decode($recvData,true);
            if(!empty($parseRecvData))
            {
               $md5 = md5($parseRecvData['cmd'] . $parseRecvData['key'] . $parseRecvData['uri']);
               if($md5 == $parseRecvData['md5'] && $parseRecvData['cmd'] == CmdDefine::CMD_REGISTER_REQ_AND_RESP)
               {
                    echo '获取注册中心！ Address:' . $parseRecvData['uri'] . PHP_EOL;
                    $this->connectToReisterServer($parseRecvData, $server, $workerId);
               }
               else
               {
                    echo 'MD5 校验失败！Error:' . $parseRecvData['errMsg'] . PHP_EOL;
                    $this->closeServer();
               }
            }
            else
           {
                echo '数据解析失败！' . PHP_EOL;
                $this->closeServer();
           }
        }
    }


    private function connectToReisterServer($urlData,$server,$workerId)
    {
        $scheme = parse_url($urlData['uri']);
        $this->_registerConnClient = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);

        $this->_registerConnClient->set($this->_swSettings['clientConf']);
        //绑定和注册中心的相关回调
        $this->_registerConnClient->on('connect', array($this, 'onRegisterConnect'));

        $this->_registerConnClient->on('receive', array($this, 'onRegisterReceive'));

        $this->_registerConnClient->on('close', array($this, 'onRegisterClose'));

        $this->_registerConnClient->on('error', array($this, 'onRegisterError'));

        $this->_registerConnClient->connect($scheme['host'], $scheme['port']);


    }

    public function onRegisterConnect($client)
    {
        echo '注册中心连接成功！' . PHP_EOL;

        $scheme = parse_url($this->_settings['uri']);
        //连接成功后发送包通知
        $data['cmd'] = CmdDefine::CMD_WORKER_REGISTER_REQ;
        $data['address'] = $scheme['scheme'] . '://' . $scheme['host'] . ':' . $scheme['port'];

        $this->_registerConnection = new TCPConnection();
        $this->_registerConnection->protocol = new BinaryProtocol($this, -1, -1);
        $this->_registerConnection->protocol->onReceivePkg = array($this,'onRegisterReceivePkg');
        $this->_registerConnection->server = $client;
        $this->_registerConnection->socket = -1;
        $this->_registerConnection->fd = -1;
        $this->_registerConnection->fromId = -1;
        $this->_registerConnection->userData = new \stdClass();

        $dataBuffer =  $this->_registerConnection->protocol->encode(json_encode($data));
        $client->send($dataBuffer);

        //注册定时器，定时发ping包,单位为ms
        $heartbeatTime = isset($this->_settings['heartbeatTime']) ? $this->_settings['heartbeatTime'] : 5;
        $heartbeatTime *= 1000;
        $this->_pingRegisterTimerId = $this->_server->swServer->tick($heartbeatTime, array($this, 'pingToRegister'));


    }

    public function onRegisterReceive($client,$data)
    {
        if(isset($this->_registerConnection))
        {
            $this->_registerConnection->protocol->fd = -1;
            $this->_registerConnection->protocol->fromId = -1;
            $this->_registerConnection->protocol->decode($this->_registerConnection, $data);
        }
        else
        {
            $this->_registerConnClient->close();
            $this->_registerConnection = null;
        }
    }

    public function onRegisterReceivePkg($connection,$context)
    {
        $msg = json_decode($context->userData->pkg,true);
        switch ($msg['cmd']) {
            case CmdDefine::CMD_PONG:

                break;
            case CmdDefine::CMD_BROADCAST_GATEWAYS:
                $this->registGateway($connection, $context);
                break;
            default:
                # code...
                break;
        }
    }
    
    public function onRegisterClose($client)
    {
        echo '注册中心连接关闭！当前服务进程ID为:' . $this->_server->swServer->worker_id . PHP_EOL;
        $this->_registerConnClient = null;

        $this->_server->swServer->clearTimer($this->_pingRegisterTimerId);
    }

    public function onRegisterError($client)
    {
        $client->close();
        $this->_registerConnClient = null;
    }
    /**
     * 向注册中心发送的心跳包为json格式
     * @return [type] [description]
     */
    public function pingToRegister()
    {
        $pingData['cmd'] = CmdDefine::CMD_PING;
        $data = json_encode($pingData);
        $buffer = $this->_registerConnection->protocol->encode($data);
        $this->_registerConnClient->send($buffer);
    }

    public function registGateway($connection,$context)
    {
        $msg = json_decode($context->userData->pkg,true);
        $this->_gatewayAddresses = array();

        foreach($msg['gateways'] as $addr)
        {
            $this->_gatewayAddresses[$addr['address']] = $addr['address'];
        }

        $this->checkGatewayConnections($this->_gatewayAddresses);
    }
    /**
     * 检查Gateway是否连接，未连接则尝试连接
     * @param  [type] $addresses [description]
     * @return [type]            [description]
     */
    public function checkGatewayConnections($addresses)
    {
        if(empty($addresses))
        {
            return;
        }
        $addrKey = array_rand($addresses,1);
        $this->connectToGateway($addresses[$addrKey]);
    }

    public function connectToGateway($address)
    {
        $scheme = parse_url($address);

        if(empty($this->_gatewayConnection))
        {
            $this->_server->logger(LoggerLevel::INFO, $address);

            $this->_server->swServer->clearTimer($this->_tryToConnectGatewayTimerId);
            $gatewayClient = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
            $gatewayClient->set($this->_swSettings['clientConf']);
            //绑定和Gateway的相关回调
            $gatewayClient->on('connect', array($this, 'onGatewayConnect'));

            $gatewayClient->on('receive', array($this, 'onGatewayReceive'));

            $gatewayClient->on('close', array($this, 'onGatewayClose'));

            $gatewayClient->on('error', array($this, 'onGatewayError'));

            $gatewayClient->connect($scheme['host'], $scheme['port']);
        }
    }
    /*****************************************注册中心相关 END*********************************************************/

    /*****************************************Gateway相关*********************************************************/
    public function onGatewayConnect($client)
    {
        //连接网关成功
        $gatewayConnection = new TCPConnection();
        $gatewayConnection->protocol = new GatewayWorkerProtocol($this, -1, -1);
        $gatewayConnection->protocol->onReceivePkg = array($this,'onGatewayReceivePkg');
        $gatewayConnection->server = $client;
        $gatewayConnection->socket = -1;
        $gatewayConnection->fd = -1;
        $gatewayConnection->fromId = -1;
        $gatewayConnection->userData = new \stdClass();
        
        $this->_gatewayConnection = $gatewayConnection;

        //发送连接消息
        $workerId = $this->_server->swServer->worker_id;
        $connectionData = GatewayWorkerProtocol::$emptyPkg;
        $connectionData['cmd'] = CmdDefine::CMD_WORKER_GATEWAY_REQ;
        $connectionData['body'] = json_encode(array(
                'workerKey' =>"WorkerServer:{$workerId}"
            ));

        $dataBuffer = $gatewayConnection->protocol->encode($connectionData);
        $dataLength = strlen($dataBuffer);
        $headerLength = pack("N", $dataLength);

        $client->send($headerLength . $dataBuffer);
    }

    public function onGatewayReceive($client,$data)
    {
        if(isset($this->_gatewayConnection))
        {
            $this->_gatewayConnection->protocol->fd = -1;
            $this->_gatewayConnection->protocol->fromId = -1;
            $this->_gatewayConnection->protocol->decode($this->_gatewayConnection, $data);
        }
    }

    public function onGatewayReceivePkg($connection,$context)
    {
        $cmd = $context->userData->pkg['cmd'];
        if($cmd === CmdDefine::CMD_PING)
        {
            $pongData = GatewayWorkerProtocol::$emptyPkg;
            $pongData['cmd'] = CmdDefine::CMD_PONG;
            $dataBuffer = $connection->protocol->encode($pongData);
            $dataLength = strlen($dataBuffer);
            $headerLength = pack("N", $dataLength);

            $connection->server->send($headerLength . $dataBuffer);
            return;
        }else if($cmd === CmdDefine::CMD_CLIENT_CONNECTION)
        {
            return;
        }
        /*以下为测试数据*/
        $gatewayData                  = GatewayWorkerProtocol::$emptyPkg;
        $gatewayData['cmd']           = CmdDefine::CMD_SEND_TO_ONE;
        $gatewayData['connectionId'] = $context->userData->pkg['connectionId'];
        $gatewayData['body']          = '收到一个完整Client数据包';
        $dataBuffer = $connection->protocol->encode($gatewayData);
        $dataLength = strlen($dataBuffer);
        $headerLength = pack("N", $dataLength);
        $connection->server->send($headerLength . $dataBuffer);
    }

    public function onGatewayClose($client)
    {
        echo '网关连接关闭！当前服务进程ID为:' . $this->_server->swServer->worker_id . PHP_EOL;
        $this->_gatewayConnection = null;
        
        $this->_tryToConnectGatewayTimerId = $this->_server->swServer->tick(5000, array($this, 'tryToConnectGateway'));
    }

    public function onGatewayError($client)
    {
        echo socket_strerror($client->errCode) . PHP_EOL;
    }

    public function tryToConnectGateway()
    {
        echo '网关连接关闭！tryToConnectGateway' . PHP_EOL;
        $this->checkGatewayConnections($this->_gatewayAddresses);
    }
    /*****************************************Gateway相关 END*********************************************************/
}