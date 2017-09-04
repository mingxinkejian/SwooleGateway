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
class PingMsgHandler extends MsgHandler
{
    public function handlerMsg($connection,$msgPkg)
    {
        $connection->send(CmdDefine::CMD_PONG);
    }
}