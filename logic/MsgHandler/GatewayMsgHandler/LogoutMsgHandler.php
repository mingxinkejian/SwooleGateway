<?php
namespace Logic\MsgHandler\GatewayMsgHandler;

use Logic\LogicManager\MsgHandler;
use SwooleGateway\Server\Protocols\GatewayWorkerProtocol;
use SwooleGateway\Common\CmdDefine;
use SwooleGateway\Server\Context\Context;
use Logic\LogicManager\GatewayServerManager;


/**
 * 登出
 */
class LogoutMsgHandler extends MsgHandler
{
    public function registProtocols()
    {

    }

    public function handlerMsg($connection,$request,$context)
    {

    }
}
