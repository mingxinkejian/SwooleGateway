<?php

namespace Logic\MsgHandler\GatewayMsgHandler;

use Logic\LogicManager\MsgHandler;
use SwooleGateway\Server\Protocols\GatewayWorkerProtocol;
use SwooleGateway\Common\CmdDefine;
use SwooleGateway\Server\Context\Context;
use Logic\LogicManager\GatewayServerManager;

use Logic\Protocol\ProtocolCmd;
use Logic\Protocol\PingReq;
use Logic\Protocol\PingResp;
use Logic\Protocol\RetCode;
/**
 * 心跳
 */
class PingMsgHandler extends MsgHandler
{
    public function registProtocols()
    {
        $this->_protocolMappers[ProtocolCmd::CMD_PING][1] = MsgHandler::MSG_REQ_NAMESPACE . "PingReq";
    }

    public function handlerMsg($connection,$request,$context)
    {
        $request->mergeFromString($context->userData->pkg);
        $svrTime = time();
        $pongResp = new PingResp();
        $pongResp->setRet(RetCode::SUCCESS);
        $pongResp->setSvrTime($svrTime);

        GatewayServerManager::getInstance()->sendMsgToClient($connection,ProtocolCmd::CMD_PONG,$pongResp->serializeToString());
    }
}