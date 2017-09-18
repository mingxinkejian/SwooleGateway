<?php

/**
 * @Author: Ming ming
 * @Date:   2017-09-09 14:57:57
 * @Last Modified by:   Ming ming
 * @Last Modified time: 2017-09-17 18:47:50
 */
namespace Logic\LogicManager;

use SwooleGateway\DataBase\Redis;
use SwooleGateway\Common\CmdDefine;
use Logic\Protocol\ProtocolCmd;

use Logic\MsgHandler\WorkerMsgHandler\LoginMsgHandler;
use Logic\Protocol\LoginReq;
use Logic\Protocol\LoginResp;
use Logic\Protocol\RetCode;
use Logic\Protocol\LoginType;
use Logic\Protocol\AccountInfo;

class WorkerServerManager extends Singleton
{
    private $_logicMapper = array();
    public $dbManager;

    public $dbRedis;
    public $tokenRedis;

    public function init()
    {
        
    }

    public function initRedis($redisConfig)
    {
        $this->dbRedis = new Redis($redisConfig);
        $this->tokenRedis = new Redis($redisConfig);
        $this->dbRedis->select(0);
        $this->tokenRedis->select(1);
    }

    public function registerMsgHandlerMapper()
    {
        //遍历MsgHandler文件夹中的所有文件，并且获取处理类
        //
        $this->_logicMapper[ProtocolCmd::CMD_LOGIN_REQ] = new LoginMsgHandler(ProtocolCmd::CMD_LOGIN_REQ);
    }

    public function getMsgHandler($msgId)
    {
        if(isset($this->_logicMapper[$msgId]))
        {
            return $this->_logicMapper[$msgId];
        }

        return null;
    }

    public function pingDB()
    {
        $this->dbRedis->ping();
        $this->tokenRedis->ping();
    }
}