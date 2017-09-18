<?php

namespace Logic\MsgHandler\WorkerMsgHandler;

use Logic\LogicManager\MsgHandler;
use SwooleGateway\Server\Protocols\GatewayWorkerProtocol;
use SwooleGateway\Common\CmdDefine;
use SwooleGateway\Server\Context\Context;

use Logic\Protocol\RetCode;
use Logic\Protocol\ProtocolCmd;
use Logic\Protocol\LoginResp;

class LoginMsgHandler extends MsgHandler
{
    public function registProtocols()
    {
        $this->_protocolMappers[ProtocolCmd::CMD_LOGIN_REQ][1] = MsgHandler::MSG_REQ_NAMESPACE . "AccountInfo";
    }

    public function handlerMsg($connection,$request,$context)
    {
        $request->mergeFromString($context->userData->pkg);

        $gatewayData                    = GatewayWorkerProtocol::$emptyPkg;
        $gatewayData['cmd']             = CmdDefine::CMD_SEND_TO_ONE;
        $gatewayData['connectionId']    = Context::$connectionId;

        $loginResp = new LoginResp();
        $loginResp->setRet(RetCode::SUCCESS);
        $loginResp->setSvrTime(time());
        $loginResp->setVersion('1.0.0');
        $loginResp->setExtMsg('亲爱的用户，this message is send from worker！');
        $gatewayData['body'] = pack("N",ProtocolCmd::CMD_LOGIN_RESP) . $loginResp->serializeToString();

        Context::$connection->send($gatewayData);
    }
}