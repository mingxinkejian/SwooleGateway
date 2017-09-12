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
use Logic\GatewayLogic;

/**
* 
*/
class GatewayServer extends GatewayObject
{
    /**
     * 注册中心信息
     * @var [type]
     */
    public $registerConnClient;
    public $registerConnection;
    public $pingRegisterTimerId;
    public $tryToConnectRegisterTimerId = -1;

    public $pingWorkerTimerId;
    /**
     * 后端worker连接信息
     * @var [type]
     */
    public $workerConnections = array();
    public $workerKeyConnections = array();
    /**
     * uId绑定connection信息
     * 每个uId可以绑定多个connection
     * [uId][connection]
     * @var [type]
     */
    public $uIdClientConnections = array();

    public $clientConnections = array();

    public $groupConnections = array();

    public $innerWorkerUri;

    public function __construct($config,$mode = SWOOLE_BASE)
    {
        $this->initServer($config, $mode);
        $this->addInnerWorkerListener();
    }

    private function initServer($config,$mode)
    {
        $this->_settings = $config;
        $this->parseConfig($config,$mode);
        $this->innerWorkerUri = parse_url($config['innerUri']);

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
        GatewayLogic::onServerStart($this);
        // 注册gateway的内部通讯地址到register，worker 去连这个地址，以便 gateway 与 worker 之间建立起 TCP 长连接
        $this->connectRegisterAddress();
        // 向Worker发送心跳包
        $this->registerSendWorkerPing($server,$workerId);
    }

    /**
     * 收到完整Client连接
     * @param  [type] $connection [description]
     * @return [type]          [description]
     */
    public function onAccept($connection)
    {
        $this->_server->logger(LoggerLevel::DEBUG,'onAccept fd:' . $connection->fd);
        //获取客户端连接信息
        $swConnInfo = $connection->getConnectionInfo();
        if(empty($swConnInfo))
        {
            //连接不存在或已关闭，返回，不做处理
            return;
        }

        $connection->userData->gatewayHeader = array(
            'localIp'       =>  ip2long($this->_server->getDefaultHost()),
            'localPort'     =>  $this->innerWorkerUri['port'],
            'clientIp'      =>  ip2long($swConnInfo['remote_ip']),
            'clientPort'    =>  $swConnInfo['remote_port'],
            'connectionId'  =>  $connection->fd,
            'gatewayPort'   =>  $this->_server->getDefaultPort(),
            );

        //保存客户端连接对象
        $this->clientConnections[$connection->fd] = $connection;

        //在此处处理客户端连接
        GatewayLogic::onClientConnect($this, $connection);

    }
    /**
     * 收到客户端关闭信息
     * @param  [type] $connection [description]
     * @return [type]          [description]
     */
    public function onClose($connection)
    {
        if(!empty($this->clientConnections[$connection->fd]))
        {
            //向Worker发送连接关闭的消息
            $this->sendToWorker(CmdDefine::CMD_CLIENT_CLOSE,$connection,$connection->userData->gatewayHeader);
            //清理连接
            unset($this->clientConnections[$connection->fd]);

            //清理uId数据
            if(isset($connection->uId) && !empty($this->uIdClientConnections[$connection->uId]))
            {
                unset($this->uIdClientConnections[$connection->uId][$connection->fd]);
                if(empty($this->uIdClientConnections[$connection->uId]))
                {
                    unset($this->uIdClientConnections[$connection->uId]);
                }
            }
            //清理group数据
            if(!empty($connection->groups))
            {
                foreach($connection->groups as $group)
                {
                    unset($this->groupConnections[$group][$connection->fd]);
                    if(empty($this->groupConnections[$group]))
                    {
                        unset($this->groupConnections[$group]);
                    }
                }
            }
        }
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
        //在此处处理收到的包，拆包后处理是否转发
        GatewayLogic::onClientReceivePkg($this, $connection, $context);
        
    }

    public function sendToWorker($cmd,$clientConntion,$gatewayHeader,$data = '')
    {
        $gatewayData = $gatewayHeader;
        $gatewayData['cmd'] = $cmd;
        $gatewayData['body'] = $data;
        $gatewayData['extData'] = '';

        if($this->workerConnections)
        {
            //根据路由规则，选择一个Worker把请求转发

            $workerConnection = $this->getClientToWorker($clientConntion,$cmd,$data);
            if(isset($workerConnection))
            {
                $workerConnection->send($gatewayData);
            }
        }
        else
        {

        }

        return true;
    }

    public function sendClientMsgToWorker($cmd,$clientConntion,$gatewayHeader,$protocolCmd,$data = '')
    {
        $gatewayData = $gatewayHeader;
        $gatewayData['cmd'] = $cmd;
        $gatewayData['body'] = $protocolCmd . $data;
        $gatewayData['extData'] = '';

        if($this->workerConnections)
        {
            //根据路由规则，选择一个Worker把请求转发

            $workerConnection = $this->getClientToWorker($clientConntion,$cmd,$data);
            if(isset($workerConnection))
            {
                $workerConnection->send($gatewayData);
            }
        }
        else
        {

        }

        return true;
    }

    public function getClientToWorker($clientConnection,$cmd,$buffer)
    {
        if(!isset($clientConnection->key) || !isset($this->workerKeyConnections[$clientConnection->key]))
        {
            $clientConnection->key = array_rand($this->workerKeyConnections);
        }
        return $this->workerKeyConnections[$clientConnection->key];
    }

    public function bindClientToWorker($clientConnection,$gameSvrId)
    {
        if(!isset($clientConnection->key) || !isset($this->workerKeyConnections[$clientConnection->key]))
        {
            $clientConnection->key = $gameSvrId;
            return true;
        }

        return false;
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
            $this->_server->logger(LoggerLevel::NOTICE, '注册中心连接失败！');
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
                    $this->_server->logger(LoggerLevel::NOTICE, '获取注册中心！ Address:' . $parseRecvData['uri']);
                    $this->connectToReisterServer($parseRecvData);
               }
               else
               {
                    $this->_server->logger(LoggerLevel::NOTICE, 'MD5 校验失败！Error:' . $parseRecvData['errMsg']);
                    $this->closeServer();
               }
            }
            else
           {
                $this->_server->logger(LoggerLevel::NOTICE, '数据解析失败！');
                $this->closeServer();
           }
        }
    }


    private function connectToReisterServer($urlData)
    {
        $scheme = parse_url($urlData['uri']);
        $this->registerConnClient = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);

        $this->registerConnClient->set($this->_swSettings['clientConf']);
        //绑定和注册中心的相关回调
        $this->registerConnClient->on('connect', array($this, 'onRegisterConnect'));

        $this->registerConnClient->on('receive', array($this, 'onRegisterReceive'));

        $this->registerConnClient->on('close', array($this, 'onRegisterClose'));

        $this->registerConnClient->on('error', array($this, 'onRegisterError'));

        $this->registerConnClient->connect($scheme['host'], $scheme['port']);


    }

    public function onRegisterConnect($client)
    {
        $this->registerConnection = new TCPConnection();
        $this->registerConnection->protocol = new BinaryProtocol($this, -1, -1);
        $this->registerConnection->protocol->onReceivePkg = array($this,'onRegisterReceivePkg');
        $this->registerConnection->server = $client;
        $this->registerConnection->socket = -1;
        $this->registerConnection->fd = -1;
        $this->registerConnection->fromId = -1;
        $this->registerConnection->userData = new \stdClass();

        $scheme = $this->innerWorkerUri;
        //连接成功后发送包通知
        $data['cmd'] = CmdDefine::CMD_GATEWAY_REGISTER_REQ;
        $data['address'] = $scheme['scheme'] . '://' . $scheme['host'] . ':' . $scheme['port'];
        $data['gatewaySvrId'] = $this->_settings['gatewaySvrId'];

        $this->registerConnection->send(json_encode($data));

        //注册定时器，定时发ping包,单位为ms
        $heartbeatTime = isset($this->_settings['heartbeatTime']) ? $this->_settings['heartbeatTime'] : 5;
        $heartbeatTime *= 1000;
        $this->pingRegisterTimerId = $this->_server->swServer->tick($heartbeatTime, array($this, 'pingToRegister'));

        if($this->tryToConnectRegisterTimerId > 0)
        {
            $this->_server->swServer->clearTimer($this->tryToConnectRegisterTimerId);
            $this->tryToConnectRegisterTimerId = -1;
            $this->_server->logger(LoggerLevel::INFO, '注册中心连接重连成功！Address : ' . $this->_settings['uri']);
        }
    }

    public function onRegisterReceive($client,$data)
    {
        if(isset($this->registerConnection))
        {
            $this->registerConnection->protocol->fd = -1;
            $this->registerConnection->protocol->fromId = -1;
            $this->registerConnection->protocol->decode($this->registerConnection, $data);
        }
        else
        {
            $this->registerConnClient->close();
            $this->registerConnection = null;
        }
    }

    public function onRegisterReceivePkg($connection,$context)
    {
        $msg = json_decode($context->userData->pkg,true);
        switch ($msg['cmd']) {
            case CmdDefine::CMD_PONG:

                break;
            default:
                # code...
                break;
        }
    }
    
    public function onRegisterClose($client)
    {
        $this->_server->logger(LoggerLevel::ERROR, '注册中心连接关闭！当前服务进程ID为:' . $this->_server->swServer->worker_id);
        $this->registerConnClient = null;

        $this->_server->swServer->clearTimer($this->pingRegisterTimerId);

        $this->tryToConnectRegisterTimerId = $this->_server->swServer->tick(5000, array($this, 'tryConnectToRegister'));
    }

    public function onRegisterError($client)
    {
        $client->close();
        $this->registerConnClient = null;
    }
    /**
     * 向注册中心发送的心跳包为json格式
     * @return [type] [description]
     */
    public function pingToRegister()
    {
        $pingData['cmd'] = CmdDefine::CMD_PING;
        $data = json_encode($pingData);
        $this->registerConnection->send($data);
    }

    public function tryConnectToRegister()
    {
        $this->_server->logger(LoggerLevel::INFO, '注册中心连接关闭！ 正在重连！');
        $this->connectRegisterAddress();
    }

    /*****************************************注册中心相关 END*********************************************************/

    /*****************************************内网监听端口*********************************************************/
    /**
     * 监听Worker内网端口
     * 内网监听端口为配置的外网端口+1
     */
    public function addInnerWorkerListener()
    {
        $scheme = $this->innerWorkerUri;
        $innerWorkerListenerUri = $scheme['scheme'] . '://' . $scheme['host'] . ':' . $scheme['port'];
        $listener = $this->_server->addListener($innerWorkerListenerUri, SWOOLE_SOCK_TCP);

        $listener->on('connect', array($this,'innerWorkerConnect'));

        $listener->on('receive', array($this,'innerWorkerReceive'));

        $listener->on('close', array($this,'innerWorkerClose'));
    }

    public function innerWorkerConnect($server,$fd,$fromId)
    {
        //获取Worker连接信息
        $swConnInfo = $server->connection_info($fd);
        if(empty($swConnInfo))
        {
            //连接不存在或已关闭，返回false，不做处理
            return;
        }

        $connection = new TCPConnection();
        $connection->protocol = new GatewayWorkerProtocol($this,$fd,$server->worker_id);
        $connection->server = $server;
        $connection->socket = $fd;
        $connection->fromId = $fromId;
        $connection->fd = $fd;
        $connection->userData = new \stdClass();
        $connection->protocol->onReceivePkg = array($this,'onInnerWorkerReceivePkg');

        $this->workerConnections[$fd] = $connection;
    }

    public function innerWorkerReceive($server,$fd,$fromId,$data)
    {
        if(isset($this->workerConnections[$fd]))
        {
            $connection = $this->workerConnections[$fd];
            $connection->protocol->fd = $fd;
            $connection->protocol->fromId = $fromId;
            $connection->protocol->decode($connection, $data);
        }
        else
        {
            $server->close($fd, true);
        }
    }

    public function innerWorkerClose($server,$fd)
    {
        $connection = $this->workerConnections[$fd];
        unset($this->workerConnections[$fd]);
        unset($this->workerKeyConnections[$connection->gameSvrId]);
    }
    /**
     * 正常情况下，Worker端是不发送ping的，心跳只有Gateway来发
     * @param  [type] $context [description]
     * @return [type]          [description]
     */
    public function onInnerWorkerReceivePkg($connection,$context)
    {
        //收到一个完整包之后处理
        $msgPkg = $context->userData->pkg;
        GatewayLogic::onInnerWorkerReceivePkg($this, $connection, $msgPkg);
    }

    public function registWorker($connection,$msgPkg)
    {
        $swConnInfo = $connection->getConnectionInfo();
        $workerKey = json_decode($msgPkg['body'], true);
        $gameSvrId = $this->splitSvrId($workerKey['gameSvrId'])[3];
        $key = $swConnInfo['remote_ip'] . ':' . $workerKey['workerKey'];
        //判断是否存在
        if(isset($this->workerKeyConnections[$gameSvrId]))
        {
            $this->_server->serverClose($connection->fd);
            return;
        }
        $connection->key = $key;
        $connection->gameSvrId = $gameSvrId;
        $this->_server->logger(LoggerLevel::INFO,'Worker连接 Key: ' . $key . ' gameSvrId:' . $workerKey['gameSvrId']);
        $this->workerKeyConnections[$gameSvrId] = $connection;

        $respData = GatewayWorkerProtocol::$emptyPkg;
        $respData['cmd'] = CmdDefine::CMD_WORKER_GATEWAY_RESP;
        $connection->send($respData);
    }

    public function sendDataFromWorkerToClient($connection, $msgPkg)
    {
        $key = $msgPkg['connectionId'];
        if(!array_key_exists($key,  $this->clientConnections))
        {
            return;
        }
        $clientConnection = $this->clientConnections[$key];
        if(isset($clientConnection))
        {
            $clientConnection->send($msgPkg['body']);
        }
    }

    /*****************************************内网监听端口 END********************************************************/

    /*****************************************ServiceServer相关*********************************************************/
    
    /**
     * 注册向Worker发送心跳的定时器
     * @param  [type] $server   [description]
     * @param  [type] $workerId [description]
     * @return [type]           [description]
     */
    public function registerSendWorkerPing($server,$workerId)
    {
        //注册定时器，定时发ping包,单位为ms
        $heartbeatTime = isset($this->_settings['heartbeatTime']) ? $this->_settings['heartbeatTime'] : 5;
        $heartbeatTime *= 1000;

        $this->pingWorkerTimerId = $this->_server->swServer->tick($heartbeatTime, array($this, 'pingToWorker'));
    
    }
    /**
     * 向后端Worker发送心跳包为二进制格式
     * @return [type] [description]
     */
    public function pingToWorker()
    {
        $pingData = GatewayWorkerProtocol::$emptyPkg;
        $pingData['cmd'] = CmdDefine::CMD_PING;

        foreach($this->workerConnections as $connection)
        {
            $connection->send($pingData);
        }

        GatewayLogic::pingDB();
    }

    /*****************************************ServiceServer相关 END********************************************************/
}