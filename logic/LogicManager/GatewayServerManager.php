<?php
namespace Logic\LogicManager;

use Logic\MsgHandler\GatewayMsgHandler\PingMsgHandler;
use Logic\MsgHandler\GatewayMsgHandler\AuthLoginMsgHandler;
use SwooleGateway\DataBase\Redis;
use SwooleGateway\Common\CmdDefine;
use Logic\Define\LogicCmdDefine;

class GatewayServerManager extends Singleton
{
    private $_logicMapper = array();
    public $redisManager;

    public function init()
    {
        echo 'GatewayServerManager init ' . PHP_EOL;
    }
    /**
     * 将业务逻辑注册根据CMD_ID注册
     * @return [type] [description]
     */
    public function registerMsgHandlerMapper()
    {
        //遍历MsgHandler文件夹中的所有文件，并且获取处理类
        $this->_logicMapper[CmdDefine::CMD_PING] = new PingMsgHandler(CmdDefine::CMD_PING);
        $this->_logicMapper[LogicCmdDefine::LOGIC_CMD_LOGIN] = new AuthLoginMsghandler(LogicCmdDefine::LOGIC_CMD_LOGIN);
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
}