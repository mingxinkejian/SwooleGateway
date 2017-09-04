<?php

namespace Logic\MsgHandler;

use Logic\LogicManager\MsgHandler;
use SwooleGateway\Server\Protocols\GatewayWorkerProtocol;
use SwooleGateway\Common\CmdDefine;
use SwooleGateway\Server\Context\Context;

class LoginMsgHandler extends MsgHandler
{
    public function handlerMsg($connection,$msgPkg)
    {
        $gatewayData                    = GatewayWorkerProtocol::$emptyPkg;
        $gatewayData['cmd']             = CmdDefine::CMD_SEND_TO_ONE;
        $gatewayData['connectionId']    = Context::$connectionId;
        $gatewayData['body']            = 'this message is send from worker';

        Context::$connection->send($gatewayData);
    }
}