<?php

namespace Logic\MsgHandler;

use Logic\LogicManager\MsgHandler;
use SwooleGateway\Server\Protocols\GatewayWorkerProtocol;
use SwooleGateway\Common\CmdDefine;
use SwooleGateway\Server\Context\Context;

class LoginMsgHandler extends MsgHandler
{
    public function handlerMsg($msgPkg)
    {
        $gatewayData                    = GatewayWorkerProtocol::$emptyPkg;
        $gatewayData['cmd']             = CmdDefine::CMD_SEND_TO_ONE;
        $gatewayData['connectionId']    = Context::$connectionId;
        $gatewayData['body']            = json_decode(substr($msgPkg, 4),true)['swooleClient'];

        Context::$connection->send($gatewayData);
    }
}