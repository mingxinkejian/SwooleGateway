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

use SwooleGateway\Common\CmdDefine;
/**
* 注册中心服务器
* 支持两种
* socket和websocket
* 注册中心因为没有什么负载压力，所以使用单进程处理
*/
class RegisterServer extends GatewayObject
{
    /**
     * 所有Gateway的连接
     * @var array
     */
    protected $_gatewayConnections = array();
    /**
     * 所有worker的连接
     * @var array
     */
    protected $_workerConnections = array();

    public function __construct($config,$mode = SWOOLE_BASE)
    {
        $this->initServer($config, $mode);

        $this->addClusterMakerListener();
    }

    private function initServer($config,$mode)
    {
        $this->_settings = $config;
        $this->parseConfig($config,$mode);

        $this->_server->onStart = array($this,'onStart');
        $this->_server->onAccept = array($this,'onAccept');
        $this->_server->onClose = array($this,'onClose');
        $this->_server->onReceivePkg = array($this,'onReceivePkg');
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
    /**
     * 收到完整连接
     * @param  [type] $connection [description]
     * @return [type]          [description]
     */
    public function onAccept($connection)
    {
        // echo 'onAccept fd:' . $connection->fd . PHP_EOL;
        // $this->_server->sendToSocket($connection->fd, 'hello Server!');
    }

    /**
     * 收到关闭信息
     * @param  [type] $connection [description]
     * @return [type]          [description]
     */
    public function onClose($connection)
    {
        echo 'onClose fd:' . $connection->fd . PHP_EOL;
    }

    /**
     * 收到一个完整数据包
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
        $recvData = json_decode($context->userData->pkg,true);
        if(empty($recvData['cmd']))
        {
            return $this->_server->serverClose($context->fd);
        }

        switch($recvData['cmd'])
        {
            case CmdDefine::CMD_PING:
                $this->heartbeatOfPong($recvData, $context);
                break;
            case CmdDefine::CMD_GATEWAY_REGISTER_REQ:
                $this->registGateway($connection, $recvData, $context);
                break;
            case CmdDefine::CMD_WORKER_REGISTER_REQ:
                $this->registWorker($connection, $recvData, $context);
                break;
            default:
                # code...
                break;
        }
    }
    /**
     * 心跳包响应
     * @param  [type] $context [description]
     * @return [type]          [description]
     */
    private function heartbeatOfPong($dataPkg,$context)
    {
        $sendData['cmd'] = CmdDefine::CMD_PONG;
        $this->_server->sendToSocket($context->fd, json_encode($sendData));
    }

    private function registGateway($connection,$dataPkg,$context)
    {
        $connInfo = new \stdClass();
        $connInfo->fd = $connection->fd;
        $connInfo->address = $dataPkg['address'];
        $this->_gatewayConnections[$connection->id] = $connInfo;
        $this->broadcastGatewayToWorker();
    }

    private function registWorker($connection,$dataPkg,$context)
    {
        $connInfo = new \stdClass();
        $connInfo->fd = $connection->fd;
        $connInfo->address = $dataPkg['address'];
        $this->_workerConnections[$connection->id] = $connInfo;

        $this->broadcastGatewayToWorker($connection);
    }

    /*****************************************集群监听相关*********************************************************/
    /**
     * 添加监听组网的广播
     * 组网流程：
     * 1、注册中心启动
     * 2、客户端发送广播寻找注册中心
     * 发送广播是根据配置文件中的clusterMakerKey来进行匹配的，不同项目组网key不同
     * 3、注册中心收到广播后验证数据包
     * 4、数据包没问题的情况下，回包，提供连接地址
     * 5、客户端发起长连接请求
     */
    protected function addClusterMakerListener()
    {
        $listener = $this->_server->addListener($this->_settings['broadcastUri'], SWOOLE_SOCK_UDP);

        $listener->on('connect', array($this,'clusterMakerConnect'));

        $listener->on('receive', array($this,'clusterMakerReceive'));

        $listener->on('close', array($this,'clusterMakerClose'));

        $listener->on('packet', array($this,'clusterMakerPacket'));
    }
    
    public function clusterMakerConnect($server,$fd)
    {

    }

    public function clusterMakerReceive($server,$fd,$fromId,$data)
    {
        
    }

    public function clusterMakerPacket($server,$data,$addr)
    {
        // var_dump($data, $addr);
        $parseRecvData = json_decode($data, true);
        if(empty($parseRecvData))
        {
            return;
        }

        if($parseRecvData['cmd'] != CmdDefine::CMD_REGISTER_REQ_AND_RESP)
        {
            return;
        }

        $md5 = md5($parseRecvData['cmd'] . $parseRecvData['key']);

        if($md5 != $parseRecvData['md5'])
        {
            return;
        }

        if($parseRecvData['cmd'] == CmdDefine::CMD_REGISTER_REQ_AND_RESP)
        {
            $sendData['cmd'] = CmdDefine::CMD_REGISTER_REQ_AND_RESP;
        }
        else
        {
            $sendData['cmd'] = CmdDefine::CMD_ERROR;
            $sendData['errMsg'] = '收到数据包错误！';
        }

        $sendData['key'] = $this->_settings['clusterMakerKey'];
        $sendData['uri'] = $this->_settings['uri'];
        $sendData['md5'] = md5($sendData['cmd'] . $sendData['key'] . $sendData['uri']);
        $server->sendto($addr['address'],$addr['port'], json_encode($sendData));
    }

    public function clusterMakerClose($server,$fd)
    {
        
    }

    /*****************************************集群监听相关 END*********************************************************/

    /**
     * 向后端Worker广播Gateway内部通讯地址
     * 每次后端Worker连接注册中心时广播一次
     * 每次Gateway连接注册中心时广播一次
     * @return [type] [description]
     */
    public function broadcastGatewayToWorker($connection = null)
    {
        $data['cmd'] = CmdDefine::CMD_BROADCAST_GATEWAYS;
        $data['gateways'] = array();

        foreach($this->_gatewayConnections as $value)
        {
            $gateway = array();
            $gateway['address'] = $value->address;
            array_push($data['gateways'], $gateway);
        }

        if($connection != null)
        {
            $this->_server->sendToSocket($connection->fd,json_encode($data));
            return;
        }

        foreach($this->_workerConnections as $value)
        {
            $this->_server->sendToSocket($value->fd,json_encode($data));
        }
    }
}