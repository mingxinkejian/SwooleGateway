<?php

/**
 * @Author: Ming ming
 * @Date:   2017-09-09 14:57:57
 * @Last Modified by:   Ming ming
 * @Last Modified time: 2017-09-09 16:57:13
 */
namespace Logic\LogicManager;

use Logic\MsgHandler\LoginMsgHandler;

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