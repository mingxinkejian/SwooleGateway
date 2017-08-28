<?php

namespace Logic\LogicManager;
/**
* 所有消息的基类，MsgHandler文件夹下的所有Handler都继承此类
*/
abstract class MsgHandler
{
    public $_msgId;

    public function __construct($msgId)
    {
        $this->_msgId = $msgId;
    }

    public abstract function handlerMsg($msgPkg);
}