<?php
namespace Logic\LogicManager;

use SwooleGateway\DataBase\Redis;
use SwooleGateway\Common\CmdDefine;
use Logic\Protocol\ProtocolCmd;

use Logic\MsgHandler\GatewayMsgHandler\PingMsgHandler;
use Logic\MsgHandler\GatewayMsgHandler\AuthLoginMsgHandler;
use Logic\MsgHandler\GatewayMsgHandler\LogoutMsgHandler;
use Logic\MsgHandler\GatewayMsgHandler\RegistMsgHandler;

class GatewayServerManager extends Singleton
{
    private $_logicMapper = array();
    public $redisManager;

    public function init()
    {

    }
    /**
     * 将业务逻辑注册根据CMD_ID注册
     * @return [type] [description]
     */
    public function registerMsgHandlerMapper()
    {
        //遍历MsgHandler文件夹中的所有文件，并且获取处理类
        $this->_logicMapper[ProtocolCmd::CMD_PING]          = new PingMsgHandler(ProtocolCmd::CMD_PING);

        $this->_logicMapper[ProtocolCmd::CMD_REGIST_REQ]    = new RegistMsgHandler(ProtocolCmd::CMD_REGIST_REQ);
        $this->_logicMapper[ProtocolCmd::CMD_LOGIN_REQ]     = new AuthLoginMsghandler(ProtocolCmd::CMD_LOGIN_REQ);
        $this->_logicMapper[ProtocolCmd::CMD_LOGOUT_REQ]    = new LogoutMsgHandler(ProtocolCmd::CMD_LOGOUT_REQ);
    }

    public function initRedis($redisConfig)
    {
        $this->redisManager = new Redis($redisConfig);
    }

    public function getMsgHandler($msgId)
    {
        if(isset($this->_logicMapper[$msgId]))
        {
            return $this->_logicMapper[$msgId];
        }

        return null;
    }

    public function sendMsgToClient($clientConnection,$cmd,$msg)
    {
        $sendBody = $cmd . $msg;
        $clientConnection->send($sendBody);
    }
}