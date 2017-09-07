<?php

namespace Logic\LogicManager;

use Logic\MsgHandler\LoginMsgHandler;
/**
* 
*/
class WorkerServerManager extends Singleton
{
    private $_logicMapper = array();
    public $dbManager;

    public function init()
    {
        
    }

    public function registerMsgHandlerMapper()
    {
        //遍历MsgHandler文件夹中的所有文件，并且获取处理类
        //
        $this->_logicMapper[1000] = new LoginMsgHandler(1000);
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