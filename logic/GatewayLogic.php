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
namespace Logic;

use SwooleGateway\Common\CmdDefine;
use SwooleGateway\Server\Protocols\GatewayWorkerProtocol;
use SwooleGateway\Logger\LoggerLevel;
use Logic\LogicManager\GatewayServerManager;
use SwooleGateway\Server\Context\Context;

/**
* 
*/
class GatewayLogic
{
    public static function onServerStart($gatewayServer)
    {
        GatewayServerManager::getInstance()->registerMsgHandlerMapper();
        GatewayServerManager::getInstance()->initRedis($gatewayServer->_settings['redisConf']);
    }

    public static function pingDB()
    {
        GatewayServerManager::getInstance()->redisManager->ping();
    }

    public static function onClientConnect($gatewayServer,$connection)
    {
        //把客户端连接转发给后端
        $gatewayServer->sendToWorker(CmdDefine::CMD_CLIENT_CONNECTION,$connection,$connection->userData->gatewayHeader);
    }

    public static function onClientReceivePkg($gatewayServer,$connection,$context)
    {
        //收到客户端发送的数据包时，读取包的前2个字节，判断是给网关发送还是给游戏服发送
        $gatewayCmd = unpack("ngatewayCmd", substr($context->userData->pkg, 0,2));
        $context->userData->pkg = substr($context->userData->pkg,2);
        switch ($gatewayCmd['gatewayCmd']) {
            case CmdDefine::CMD_CLIENT_GATEWAY_MESSAGE:
                {
                    $protocolId = unpack("NprotocolId", substr($context->userData->pkg, 0,4));
                    $handler = GatewayServerManager::getInstance()->getMsgHandler($protocolId['protocolId']);
                    if(!empty($handler))
                    {
                        $handler->_server = $gatewayServer;
                        $handler->handlerMsg($connection, substr($context->userData->pkg,4));
                    }
                    else
                    {
                        $gatewayServer->_server->logger(LoggerLevel::ERROR,"未找到MsgId:[{$protocolId['protocolId']}]的MsgHandler");
                    }
                }
                break;
            case CmdDefine::CMD_CLIENT_WORKER_MESSAGE:
                $gatewayServer->sendToWorker(CmdDefine::CMD_CLIENT_MESSAGE, $connection, $connection->userData->gatewayHeader, $context->userData->pkg);
                break;
            default:
                break;
        }

        
    }

    public static function onInnerWorkerReceivePkg($gatewayServer,$connection,$msgPkg)
    {
        $cmd = $msgPkg['cmd'];
        switch($cmd)
        {
            case CmdDefine::CMD_PONG:
                break;
            case CmdDefine::CMD_WORKER_GATEWAY_REQ:
                $gatewayServer->registWorker($connection, $msgPkg);
                break;
            case CmdDefine::CMD_SEND_TO_ONE:
                $gatewayServer->sendDataFromWorkerToClient($connection, $msgPkg);
                break;
            case CmdDefine::CMD_KICK:
                self::kick($gatewayServer, $connection, $msgPkg);
                break;
            case CmdDefine::CMD_DESTROY:
                self::destroy($gatewayServer, $connection, $msgPkg);
                break;
            case CmdDefine::CMD_SEND_TO_ALL:
                self::sendToAll($gatewayServer, $connection, $msgPkg);
                break;
            case CmdDefine::CMD_SET_SESSION:
                self::setSession($gatewayServer, $connection, $msgPkg);
                break;
            case CmdDefine::CMD_UPDATE_SESSION:
                self::updateSession($gatewayServer, $connection, $msgPkg);
                break;
            case CmdDefine::CMD_GET_SESSION_BY_CLIEND_ID:
                self::getSessionByClientId($gatewayServer, $connection, $msgPkg);
                break;
            case CmdDefine::CMD_GET_ALL_CLIENT_INFO:
                self::getAllClientInfo($gatewayServer, $connection, $msgPkg);
                break;
            case CmdDefine::CMD_IS_ONLINE:
                self::isOnline($gatewayServer, $connection, $msgPkg);
                break;
            case CmdDefine::CMD_BIND_UID:
                self::bindUid($gatewayServer, $connection, $msgPkg);
                break;
            case CmdDefine::CMD_UNBIND_UID:
                self::unbindUid($gatewayServer, $connection, $msgPkg);
                break;
            case CmdDefine::CMD_SEND_TO_UID:
                self::sendToUid($gatewayServer, $connection, $msgPkg);
                break;
            case CmdDefine::CMD_JOIN_GROUP:
                self::joinGroup($gatewayServer, $connection, $msgPkg);
                break;
            case CmdDefine::CMD_LEAVE_GROUP:
                self::leaveGroup($gatewayServer, $connection, $msgPkg);
                break;
            case CmdDefine::CMD_SEND_TO_GROUP:
                self::sendToGroup($gatewayServer, $connection, $msgPkg);
                break;
            case CmdDefine::CMD_GET_CLIENT_INFO_BY_GROUP:
                self::getClientInfoByGroup($gatewayServer, $connection, $msgPkg);
                break;
            case CmdDefine::CMD_GET_CLIENT_COUNT_BY_GROUP:
                self::getClientCountByGroup($gatewayServer, $connection, $msgPkg);
                break;
            case CmdDefine::CMD_GET_CLIENT_ID_BY_UID:
                self::getClientIdByUid($gatewayServer, $connection, $msgPkg);
                break;
            default:
                $errMsg = "gateway inner pack err cmd = {$cmd}";
                throw new \Exception($errMsg);
                
                break;
        }
    }

    private static function kick($gatewayServer,$connection,$msgPkg)
    {
        if(isset($gatewayServer->clientConnections[$msgPkg['connectionId']]))
        {
            $gatewayServer->clientConnections[$msgPkg['connectionId']]->close($msgPkg['body']);
        }
    }

    private static function destroy($gatewayServer,$connection,$msgPkg)
    {
        self::kick($gatewayServer, $connection, $msgPkg);
    }

    private static function sendToAll($gatewayServer,$connection,$msgPkg)
    {
        $body = $msgPkg['body'];
        $extData = $msgPkg['extData'] ? json_decode($msgPkg['extData'], true) : '';
        //广播名单，如果存在广播名单，则遍历获取并发送
        if(!empty($extData) && isset($extData['connections']))
        {
            foreach($extData['connections'] as $connectionId)
            {
                if(isset($gatewayServer->clientConnections[$connectionId]))
                {
                    $gatewayServer->clientConnections[$connectionId]->send($body);
                }
            }
        }
        else
        {
            //不存在的话，全局广播
            //如果有需要剔除的名单则判断一下
            $excludeConnectionId = !empty($extData['exclude']) ? $extData['exclude'] : null;
            foreach($gatewayServer->clientConnections as $clientConnection)
            {
                if(!isset($excludeConnectionId[$clientConnection->fd]))
                {
                    $clientConnection->send($body);
                }
            }
        }
    }

    private static function setSession($gatewayServer,$connection,$msgPkg)
    {
        if(isset($gatewayServer->clientConnections[$msgPkg['connectionId']]))
        {
            $gatewayServer->clientConnections[$msgPkg['connectionId']]->session = $msgPkg['extData'];
        }
    }

    private static function updateSession($gatewayServer,$connection,$msgPkg)
    {
        if(!isset($gatewayServer->clientConnections[$msgPkg['connectionId']]))
        {
            return;
        }
        else
        {
            if(!$gatewayServer->clientConnections[$msgPkg['connectionId']]->session)
            {
                $gatewayServer->clientConnections[$msgPkg['connectionId']]->session = $msgPkg['extData'];
                return;
            }
            $session = json_decode($this->clientConnections[$data['connectionId']]->session);
            $sessionMerge = json_decode($data['extData']);
            $session = array_merge($sessionMerge, $session);
            $this->_clientConnections[$data['connection_id']]->session = json_encode($session);
        }
    }

    private static function getSessionByClientId($gatewayServer,$connection,$msgPkg)
    {
        if(!isset($gatewayServer->clientConnections[$msgPkg['connectionId']]))
        {
            $respData = json_encode(array());
        }
        else
        {
            if(!$gatewayServer->clientConnections[$msgPkg['connectionId']]->session)
            {
                $respData = json_encode(array());
            }
            else
            {
                $respData = json_encode($gatewayServer->clientConnections[$msgPkg['connectionId']]->session);
            }
        }
        $connection->send(json_encode($respData));
    }

    private static function getAllClientInfo($gatewayServer,$connection,$msgPkg)
    {
        $clientInfoArray = array();

        foreach($gatewayServer->clientConnections as $connectionId => $clientConnection)
        {
            $clientInfoArray[$connectionId] = $clientConnection->session;
        }
        $respData = GatewayWorkerProtocol::$emptyPkg;
        $respData['cmd'] = CmdDefine::CMD_GET_ALL_CLIENT_INFO;
        $respData['body'] = json_encode($clientInfoArray);

        $connection->send($respData);
    }

    private static function isOnline($gatewayServer,$connection,$msgPkg)
    {
        $clientConnection = $gatewayServer->clientConnections[$msgPkg['connectionId']];
        if(isset($clientConnection))
        {
            $respData = $clientConnection->userData->gatewayHeader;
            $respData['cmd'] = CmdDefine::CMD_IS_ONLINE;
            $respData['body'] = true;
        }
        else
        {
            $respData = GatewayWorkerProtocol::$emptyPkg;
            $respData['connectionId'] = $msgPkg['connectionId'];
            $respData['cmd'] = CmdDefine::CMD_IS_ONLINE;
            $respData['body'] = false;
        }
    }

    private static function bindUid($gatewayServer,$connection,$msgPkg)
    {
        $uId = $msgPkg['extData'];
        if(empty($uId))
        {
            $gatewayServer->_server->logger(LoggerLevel::WARN, 'bindUid(clientId, uId) uId empty, uId=' . var_export($uId,true));
            return;
        }

        $connectionId = $msgPkg['connectionId'];
        if(!isset($gatewayServer->clientConnections[$connectionId]))
        {
            $gatewayServer->_server->logger(LoggerLevel::ERROR, 'bindUid(clientId, uId) connection is closed, uId=' . $uId);
            return;
        }
        $clientConnection = $gatewayServer->clientConnections[$connectionId];

        if(isset($clientConnection->uId))
        {
            $currentUId = $clientConnection->uId;
            unset($gatewayServer->uIdClientConnections[$currentUId][$connectionId]);
            if(empty($gatewayServer->uIdClientConnections[$currentUId]))
            {
                unset($gatewayServer->uIdClientConnections[$currentUId]);
            }
        }
        $clientConnection->uId                  = $uId;
        $gatewayServer->uIdClientConnections[$uId][$connectionId]   = $clientConnection;

        $respData = $clientConnection->userData->gatewayHeader;
        $respData['cmd'] = CmdDefine::CMD_BIND_UID;
        $respData['body'] = true;

        $connection->send($respData);
    }

    private static function unbindUid($gatewayServer,$connection,$msgPkg)
    {
        $connectionId = $msgPkg['connectionId'];

        if(isset($gatewayServer->clientConnections[$connectionId]))
        {
            $gatewayServer->_server->logger(LoggerLevel::ERROR, 'unbindUid(clientId, uId) connection is closed');
            return;
        }

        $clientConnection = $gatewayServer->clientConnections[$connectionId];
        if(isset($clientConnection->uId))
        {
            $currentUId = $clientConnection->uId;
            unset($gatewayServer->uIdClientConnections[$currentUId][$connectionId]);
            if(empty($gatewayServer->uIdClientConnections[$currentUId]))
            {
                unset($gatewayServer->uIdClientConnections[$currentUId]);
            }
            $clientConnection->uId = null;
        }

        $respData = $clientConnection->userData->gatewayHeader;
        $respData['cmd'] = CmdDefine::CMD_UNBIND_UID;
        $respData['body'] = true;

        $connection->send($respData);
    }

    private static function sendToUid($gatewayServer,$connection,$msgPkg)
    {
        $uIdArray = json_decode($msgPkg['extData'],true);
        foreach ($uIdArray as $key => $uId) {
            if(!empty($gatewayServer->uIdClientConnections[$uId]))
            {
                foreach ($gatewayServer->uIdClientConnections[$uId] as $clientConnection) {
                    $clientConnection->send($msgPkg['body']);
                }
            }
        }
    }

    private static function joinGroup($gatewayServer,$connection,$msgPkg)
    {
        $group = $msgPkg['extData'];

        if(empty($group))
        {
            $gatewayServer->_server->logger(LoggerLevel::ERROR, 'joinGroup group empty, group = ' . var_export($group,true));
            return;
        }

        $connectionId = $msgPkg['connectionId'];
        if(!isset($this->clientConnections[$connectionId]))
        {
            return;
        }

        $clientConnection = $this->clientConnections[$connectionId];
        if(!isset($clientConnection->groups))
        {
            $clientConnection->groups = array();
        }
        $clientConnection->groups[$group] = $group;
        $gatewayServer->groupConnections[$group][$connectionId] = $clientConnection;

    }

    private static function leaveGroup($gatewayServer,$connection,$msgPkg)
    {
        $group = $msgPkg['extData'];

        if(empty($group))
        {
            $gatewayServer->_server->logger(LoggerLevel::ERROR, 'leaveGroup group empty, group = ' . var_export($group,true));
            return;
        }

        $connectionId = $msgPkg['connectionId'];
        if(!isset($this->clientConnections[$connectionId]))
        {
            return;
        }

        $clientConnection = $this->clientConnections[$connectionId];
        if(!isset($clientConnection->groups[$group]))
        {
            return;
        }
        unset($clientConnection->groups[$group]);
        unset($gatewayServer->groupConnections[$group][$connectionId]);
    }

    private static function sendToGroup($gatewayServer,$connection,$msgPkg)
    {
        $body = $msgPkg['$body'];
        $extData = json_decode($msgPkg['extData'], true);
        $groupArray = $extData['group'];
        $excludeConnectionId = $extData['exclude'];

        foreach($groupArray as $group)
        {
            if(!empty($this->groupConnections[$group]))
            {
                foreach($gatewayServer->groupConnections[$group] as $clientConnection)
                {
                    if(!isset($excludeConnectionId[$clientConnection->fd]))
                    {
                        $clientConnection->send($body);
                    }
                }
            }
        }
    }

    private static function getClientInfoByGroup($gatewayServer,$connection,$msgPkg)
    {
        $group = $msgPkg['extData'];
        if(!isset($gatewayServer->groupConnections[$group]))
        {
            $respData = array();
            $connection->send(json_encode($respData));
            return;   
        }
        $clientInfoArray = array();

        foreach($$gatewayServer->groupConnections[$group] as $connectionId => $clientConnection)
        {
            $clientInfoArray[$connectionId] = $clientConnection->session;
        }

        $connection->send(json_encode($clientInfoArray));
    }

    private static function getClientCountByGroup($gatewayServer,$connection,$msgPkg)
    {
        $group = $msgPkg['extData'];
        $count = 0;
        if(!empty($group))
        {
            if(isset($gatewayServer->groupConnections[$group]))
            {
                $count = count($gatewayServer->groupConnections[$group]);
            }
        }else
        {
            $count = count($gatewayServer->clientConnections);
        }

        $connection->send($count);
    }

    private static function getClientIdByUid($gatewayServer,$connection,$msgPkg)
    {
        $uId = $msgPkg['$extData'];

        if(empty($gatewayServer->uIdClientConnections[$uId]))
        {
            $respData = json_encode(array());
        }
        else
        {
            $respData = json_encode(array_keys($this->uIdClientConnections[$uId]));
        }

        $connection->send($respData);
    }
}