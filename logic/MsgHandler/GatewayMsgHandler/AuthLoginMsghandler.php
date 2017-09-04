<?php

namespace Logic\MsgHandler\GatewayMsgHandler;

use Logic\LogicManager\MsgHandler;
use SwooleGateway\Server\Protocols\GatewayWorkerProtocol;
use SwooleGateway\Common\CmdDefine;
use SwooleGateway\Server\Context\Context;
use Logic\LogicManager\GatewayServerManager;

/**
 * 登录验证
 */
class AuthLoginMsgHandler extends MsgHandler
{
    public function handlerMsg($connection,$msgPkg)
    {
        $unpackMsg = msgpack_unpack($msgPkg);
        GatewayServerManager::getInstance()->redisManager->select(1);

        $loginData = GatewayServerManager::getInstance()->redisManager->get($unpackMsg['userName']);
        if(empty($loginData))
        {
            $registInfo['userName'] = $unpackMsg['userName'];
            $registInfo['password'] = md5($unpackMsg['password']);
            $registInfo['registTime'] = date('Y-m-d H:i:s');

            GatewayServerManager::getInstance()->redisManager->set($unpackMsg['userName'],msgpack_pack($registInfo));
            // $connection->send('注册成功！');
            $this->_server->sendToWorker(CmdDefine::CMD_CLIENT_MESSAGE,$connection,$connection->userData->gatewayHeader,pack("N",1000));
        }
        else
        {
            // $connection->send($loginData);
            $this->_server->sendToWorker(CmdDefine::CMD_CLIENT_MESSAGE,$connection,$connection->userData->gatewayHeader,pack("N",1000));
        }


    }
}