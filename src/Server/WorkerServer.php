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
use Logic\WorkerLogic;
use SwooleGateway\Server\Context\Context;
/**
* 
*/
class WorkerServer extends GatewayObject
{
    /**
     * 注册中心信息
     * @var [type]
     */
    public $_registerConnClient;
    public $_registerConnection;

    public $_pingRegisterTimerId;
    public $_tryToConnectRegisterTimerId = -1;

    public $_pingWorkerTimerId;
    public $_tryToConnectGatewayTimerId = -1;
    /**
     * 保存网关信息
     * @var array
     */
    public $_gatewayAddresses = array();
    public $_gatewayConnection;
    public $_gatewayConnectingAddress = '';

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
        // worker中不需要
        // $this->_server->onAccept = array($this, 'onAccept');
        // $this->_server->onClose = array($this, 'onClose');
        // $this->_server->onReceivePkg = array($this, 'onReceivePkg');
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
        WorkerLogic::onServerStart($this);
        // 注册gateway的内部通讯地址到register，worker 去连这个地址，以便 gateway 与 worker 之间建立起 TCP 长连接
        $this->connectRegisterAddress();
    }

    /*****************************************注册中心相关*********************************************************/
    private function connectRegisterAddress()
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
            $this->_server->logger(LoggerLevel::ERROR, '注册中心连接失败!');
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
                    $this->_server->logger(LoggerLevel::INFO, '获取注册中心！ Address:' . $parseRecvData['uri']);
                    $this->connectToReisterServer($parseRecvData);
               }
               else
               {
                    $this->_server->logger(LoggerLevel::ERROR, 'MD5 校验失败！Error:' . $parseRecvData['errMsg']);
                    $this->closeServer();
               }
            }
            else
            {
                $this->_server->logger(LoggerLevel::ERROR, '注册中心数据解析失败！recvPkg:' . $recvData);
                $this->closeServer();
            }
        }
    }


    private function connectToReisterServer($urlData)
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
        $this->_server->logger(LoggerLevel::INFO, '注册中心连接成功！');

        $scheme = parse_url($this->_settings['uri']);
        /**
         * 连接成功后发送包通知
         * 包内的信息包括游戏服地址和游戏服的ID
         */
        $data['cmd'] = CmdDefine::CMD_WORKER_REGISTER_REQ;
        $data['address'] = $scheme['scheme'] . '://' . $scheme['host'] . ':' . $scheme['port'];
        $data['gameSvrId'] = $this->_settings['gameSvrId'];

        $this->_registerConnection = new TCPConnection();
        $this->_registerConnection->protocol = new BinaryProtocol($this, -1, -1);
        $this->_registerConnection->protocol->onReceivePkg = array($this,'onRegisterReceivePkg');
        $this->_registerConnection->server = $client;
        $this->_registerConnection->socket = -1;
        $this->_registerConnection->fd = -1;
        $this->_registerConnection->fromId = -1;
        $this->_registerConnection->userData = new \stdClass();

        $this->_registerConnection->send(json_encode($data));

        //注册定时器，定时发ping包,单位为ms
        $heartbeatTime = isset($this->_settings['heartbeatTime']) ? $this->_settings['heartbeatTime'] : 5;
        $heartbeatTime *= 1000;
        $this->_pingRegisterTimerId = $this->_server->swServer->tick($heartbeatTime, array($this, 'pingToRegister'));

        if($this->_tryToConnectRegisterTimerId > 0)
        {
            $this->_server->swServer->clearTimer($this->_tryToConnectRegisterTimerId);
            $this->_tryToConnectRegisterTimerId = -1;
            $this->_server->logger(LoggerLevel::INFO, '注册中心连接重连成功！Address : ' . $this->_settings['uri']);
        }
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
        $this->_server->logger(LoggerLevel::INFO, '注册中心连接关闭！当前服务进程ID为:' . $this->_server->swServer->worker_id);
        $this->_registerConnClient = null;

        $this->_server->swServer->clearTimer($this->_pingRegisterTimerId);

        $this->_tryToConnectRegisterTimerId = $this->_server->swServer->tick(5000, array($this, 'tryConnectToRegister'));
    }

    public function onRegisterError($client)
    {
        $client->close();
        $this->_registerConnClient = null;
        $this->_server->logger(LoggerLevel::ERROR, 'onGatewayError:' . socket_strerror($client->errCode));
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

    public function tryConnectToRegister()
    {
        $this->_server->logger(LoggerLevel::INFO, '注册中心连接关闭！ 正在重连！');
        $this->connectRegisterAddress();
    }



    /*****************************************注册中心相关 END*********************************************************/

    /*****************************************Gateway相关*********************************************************/
    public function registGateway($connection,$context)
    {
        $msg = json_decode($context->userData->pkg,true);
        $this->_gatewayAddresses = array();

        foreach($msg['gateways'] as $addr)
        {
            $this->_gatewayAddresses[$addr['address']] = $addr['address'];
        }
        $this->_server->logger(LoggerLevel::DEBUG, json_encode($this->_gatewayAddresses));
        $this->checkGatewayConnections($this->_gatewayAddresses);
    }
    /**
     * 检查Gateway是否连接，未连接则尝试连接
     * @param  [type]  $addresses [description]
     * @param  boolean $isRetry   [description]
     * @return [type]             [description]
     */
    public function checkGatewayConnections($addresses,$isRetry = false)
    {
        if(empty($addresses))
        {
            return;
        }
        $addrKey = array_rand($addresses,1);
        $this->connectToGateway($addresses[$addrKey], $isRetry);
    }

    public function connectToGateway($address,$isRetry = false)
    {
        $this->_gatewayConnectingAddress = $address;
        $scheme = parse_url($address);

        if(empty($this->_gatewayConnection))
        {
            if($this->_tryToConnectGatewayTimerId > 0)
            {
                $this->_server->swServer->clearTimer($this->_tryToConnectGatewayTimerId);
                $this->_tryToConnectGatewayTimerId = -1;
                $this->_server->logger(LoggerLevel::INFO, '网关连接重连成功！Address : ' .$address);
            }
            
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

    public function onGatewayConnect($client)
    {
        $this->_server->logger(LoggerLevel::INFO, '网关连接成功！Address : ' .$this->_gatewayConnectingAddress);
        //连接网关成功
        $this->_gatewayConnection = new TCPConnection();
        $this->_gatewayConnection->protocol = new GatewayWorkerProtocol($this, -1, -1);
        $this->_gatewayConnection->protocol->onReceivePkg = array($this,'onGatewayReceivePkg');
        $this->_gatewayConnection->server = $client;
        $this->_gatewayConnection->socket = -1;
        $this->_gatewayConnection->fd = -1;
        $this->_gatewayConnection->fromId = -1;
        $this->_gatewayConnection->userData = new \stdClass();
        //发送连接消息
        $workerId = $this->_server->swServer->worker_id;
        $connectionData = GatewayWorkerProtocol::$emptyPkg;
        $connectionData['cmd'] = CmdDefine::CMD_WORKER_GATEWAY_REQ;
        $connectionData['body'] = json_encode(array(
                'workerKey' =>"WorkerServer:{$workerId}"
            ));

        $this->_gatewayConnection->send($connectionData);
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

            $connection->send($pongData);
            return;
        }else if($cmd === CmdDefine::CMD_WORKER_GATEWAY_RESP)
        {
            $this->_server->logger(LoggerLevel::INFO,"网关连接确认！！！");
            return;
        }
        //绑定上下文
        Context::$workerServer      = $this;
        Context::$clientIp          = long2ip($context->userData->pkg['clientIp']);
        Context::$clientPort        = $context->userData->pkg['clientPort'];
        Context::$localIp           = long2ip($context->userData->pkg['localIp']);
        Context::$localPort         = $context->userData->pkg['localPort'];
        Context::$connectionId      = $context->userData->pkg['connectionId'];
        Context::$clientId          = Context::addressToClientId(Context::$localIp,Context::$localPort,Context::$connectionId);
        Context::$connection        = $connection;
        //绑定数据根据cmd来调用不同接口
        switch($cmd)
        {
            case CmdDefine::CMD_CLIENT_CONNECTION:
                WorkerLogic::clientConnect();
                break;
            case CmdDefine::CMD_CLIENT_MESSAGE:
                WorkerLogic::clientMessage($context->userData->pkg['body']);
                break;
            case CmdDefine::CMD_CLIENT_CLOSE:
                WorkerLogic::clientClose();
                break;
            default:
                 
                break;
        }
        //清除上下文
        Context::clearContext();
    }

    public function onGatewayClose($client)
    {

        $this->_server->logger(LoggerLevel::WARN, '网关连接关闭！当前服务进程ID为:' . $this->_server->swServer->worker_id);
        $this->_gatewayConnection = null;
        
        $this->_tryToConnectGatewayTimerId = $this->_server->swServer->tick(5000, array($this, 'tryToConnectGateway'));
    }

    public function onGatewayError($client)
    {
        $this->_server->logger(LoggerLevel::ERROR, 'onGatewayError:' . socket_strerror($client->errCode));
    }

    public function tryToConnectGateway()
    {
        $this->_server->logger(LoggerLevel::INFO, '网关连接关闭！ 正在重连！');
        $this->checkGatewayConnections($this->_gatewayAddresses);
    }
    /*****************************************Gateway相关 END*********************************************************/
}