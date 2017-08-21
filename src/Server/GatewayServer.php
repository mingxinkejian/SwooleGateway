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
class GatewayServer extends GatewayObject
{
    /**
     * 注册中心信息
     * @var [type]
     */
    private $_registerConnClient;

    private $_pingRegisterTimerId;

    private $_pingWorkerTimerId;
    /**
     * 后端worker连接信息
     * @var [type]
     */
    private $_workerConnections = array();
    private $_workerKeyConnections = array();
    /**
     * uId绑定connection信息
     * 
     * @var [type]
     */
    private $_uIdClientConnections = array();

    private $_clientConnections = array();
    private $_clientConnectionsByFd = array();

    private $_innerWorkerUri;

    public function __construct($config,$mode = SWOOLE_BASE)
    {
        $this->initServer($config, $mode);
        $this->addInnerWorkerListener();
    }

    private function initServer($config,$mode)
    {
        $this->_settings = $config;
        $this->parseConfig($config,$mode);
        $this->_innerWorkerUri = parse_url($config['innerUri']);

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
        echo 'onAccept fd:' . $connection->fd . PHP_EOL;
        $this->_server->logger(LoggerLevel::DEBUG,'onAccept fd:' . $connection->fd);
        //获取客户端连接信息
        $swConnInfo = $connection->getConnectionInfo();
        if(empty($swConnInfo))
        {
            //连接不存在或已关闭，返回false，不做处理
            return;
        }

        $connection->id = $this->generateConnectionId();
        $connection->server = $connection->server;
        $connection->socket = $connection->socket;
        $connection->fromId = $connection->fromId;
        $connection->fd = $connection->fd;

        $connection->userData->gatewayHeader = array(
            'localIp'       =>  ip2long($this->_server->getDefaultHost()),
            'localPort'     =>  $this->_innerWorkerUri['port'],
            'clientIp'      =>  ip2long($swConnInfo['remote_ip']),
            'clientPort'    =>  $swConnInfo['remote_port'],
            'connectionId'  =>  $connection->id,
            'gatewayPort'   =>  $this->_server->getDefaultPort(),
            );

        //保存客户端连接对象
        $this->_clientConnectionsByFd[$connection->fd] = $connection;
        $this->_clientConnections[$connection->id] = $connection;

        //把客户端连接转发给后端
        $this->sendToWorker(CmdDefine::CMD_CLIENT_CONNECTION,$connection);
    }
    /**
     * 收到客户端关闭信息
     * @param  [type] $connection [description]
     * @return [type]          [description]
     */
    public function onClose($connection)
    {
        echo 'onClose fd:' . $connection->fd . PHP_EOL;

        if(!empty($this->_clientConnectionsByFd[$connection->fd]))
        {
            echo 'onClose clear connectionId fd:' . $connection->fd . PHP_EOL;
            //向Worker发送连接关闭的消息
            $this->sendToWorker(CmdDefine::CMD_CLIENT_CLOSE,$connection);
            //清理连接
            unset($this->_clientConnectionsByFd[$connection->fd]);
            unset($this->_clientConnections[$connection->id]);

            //清理uId数据
            if(isset($connection->uId) && !empty($this->_uIdClientConnections[$connection->uId]))
            {
                unset($this->_uIdClientConnections[$connection->uId][$connection->id]);
                if(empty($this->_uIdClientConnections[$connection->uId]))
                {
                    unset($this->_uIdClientConnections[$connection->uId]);
                }
            }
            //清理group数据
            //TO DO
            //暂时未加入group列表
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
        $this->sendToWorker(CmdDefine::CMD_CLIENT_MESSAGE, $connection, $context->userData->pkg);
    }

    public function sendToWorker($cmd,$connection,$data = '')
    {
        $gatewayData = $connection->userData->gatewayHeader;
        $gatewayData['cmd'] = $cmd;
        $gatewayData['body'] = $data;
        $gatewayData['extData'] = '';

        if($this->_workerConnections)
        {
            //根据路由规则，选择一个Worker把请求转发
            $workerConnection = $this->bindClientToWorker($connection,$cmd,$data);
            if(isset($workerConnection))
            {
                $this->_server->sendToSocket($workerConnection->fd,$workerConnection->protocol->encode($gatewayData));
            }
        }
        else
        {

        }

        return true;
    }

    public function bindClientToWorker($clientConnection,$cmd,$buffer)
    {
        if (!isset($clientConnection->workerKey) || !isset($this->_workerKeyConnections[$clientConnection->workerKey])) {
            $clientConnection->workerKey = array_rand($this->_workerKeyConnections);
        }
        return $this->_workerKeyConnections[$clientConnection->workerKey];
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

        $scheme = $this->_innerWorkerUri;
        //连接成功后发送包通知
        $data['cmd'] = CmdDefine::CMD_GATEWAY_REGISTER_REQ;
        $data['address'] = $scheme['scheme'] . '://' . $scheme['host'] . ':' . $scheme['port'];

        $protocol = new BinaryProtocol($client,-1,-1);
        $dataBuffer = $protocol->encode(json_encode($data));
        $client->send($dataBuffer);

        //注册定时器，定时发ping包,单位为ms
        $heartbeatTime = isset($this->_settings['heartbeatTime']) ? $this->_settings['heartbeatTime'] : 5;
        $heartbeatTime *= 1000;
        $this->_pingRegisterTimerId = $this->_server->swServer->tick($heartbeatTime, array($this, 'pingToRegister'));


    }

    public function onRegisterReceive($client,$data)
    {

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
        $dataLength = strlen($data);
        $headerLength = pack("N", $dataLength);
        $this->_registerConnClient->send($headerLength . $data);
    }


    /*****************************************注册中心相关 END*********************************************************/

    /*****************************************内网监听端口*********************************************************/
    /**
     * 监听Worker内网端口
     * 内网监听端口为配置的外网端口+1
     */
    public function addInnerWorkerListener()
    {
        $scheme = $this->_innerWorkerUri;
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
        $connection->id = $this->generateConnectionId();
        $connection->server = $server;
        $connection->socket = $fd;
        $connection->fromId = $fromId;
        $connection->fd = $fd;
        $connection->userData = new \stdClass();
        $connection->protocol->onReceivePkg = array($this,'onInnerWorkerReceivePkg');

        $this->_workerConnections[$fd] = $connection;
    }

    public function innerWorkerReceive($server,$fd,$fromId,$data)
    {
        if(isset($this->_workerConnections[$fd]))
        {
            $connection = $this->_workerConnections[$fd];
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
        $connection = $this->_workerConnections[$fd];
        unset($this->_workerConnections[$fd]);
        unset($this->_workerKeyConnections[$connection->key]);
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

        $cmd = $msgPkg['cmd'];
        switch ($cmd) {
            case CmdDefine::CMD_PONG:
                break;
            case CmdDefine::CMD_WORKER_GATEWAY_REQ:
                $this->registWorker($connection, $msgPkg);
                break;
            case CmdDefine::CMD_SEND_TO_ONE:
                $this->sendDataFromWorkerToClient($connection, $msgPkg);
                break;
            default:

                break;
        }
    }

    public function registWorker($connection,$msgPkg)
    {
        $swConnInfo = $connection->getConnectionInfo();
        $workerKey = json_decode($msgPkg['body'], true);
        $key = $swConnInfo['remote_ip'] . ':' . $workerKey['workerKey'];
        //判断是否存在
        if(isset($this->_workerKeyConnections[$key]))
        {
            $this->_server->serverClose($connection->fd);
            return;
        }
        $connection->key = $key;
        $this->_workerKeyConnections[$key] = $connection;
    }

    public function sendDataFromWorkerToClient($connection, $msgPkg)
    {
        $clientConnection = $this->_clientConnections[$msgPkg['connectionId']];
        if(isset($clientConnection))
        {
            $this->_server->sendToSocket($clientConnection->fd,$msgPkg['body']);
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

        $this->_pingWorkerTimerId = $this->_server->swServer->tick($heartbeatTime, array($this, 'pingToWorker'));
    
    }
    /**
     * 向后端Worker发送心跳包为二进制格式
     * @return [type] [description]
     */
    public function pingToWorker()
    {
        $pingData = GatewayWorkerProtocol::$emptyPkg;
        $pingData['cmd'] = CmdDefine::CMD_PING;

        $protocol = new GatewayWorkerProtocol($this, -1, $this->_server->swServer->worker_id);
        $dataBuffer = $protocol->encode($pingData);

        foreach($this->_workerConnections as $connection)
        {
            $this->_server->sendToSocket($connection->fd, $dataBuffer);
        }
    }

    /*****************************************ServiceServer相关 END********************************************************/
}