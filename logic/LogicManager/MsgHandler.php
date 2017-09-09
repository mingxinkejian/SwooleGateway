<?php

/**
 * @Author: Ming ming
 * @Date:   2017-09-08 11:31:30
 * @Last Modified by:   Ming ming
 * @Last Modified time: 2017-09-09 14:58:21
 * 
 * 所有消息的基类，MsgHandler文件夹下的所有Handler都继承此类
 * 
 */
namespace Logic\LogicManager;

abstract class MsgHandler
{
    public $_msgId;

    public $_server;

    const MSG_REQ_NAMESPACE = "Logic\\Protocol\\";

    public $_protocolMappers = array();

    public function __construct($msgId)
    {
        $this->_msgId = $msgId;
        $this->registProtocols();
    }
    /**
     * 消息处理函数
     * @param  [type] $connection [description]
     * @param  [type] $request    [description]
     * @param  [type] $context    [description]
     * @return [type]             [description]
     */
    public abstract function handlerMsg($connection,$request,$context);
    /**
     * 注册对应的请求协议
     * @return [type] [description]
     */
    public abstract function registProtocols();
    /**
     * 根据cmd创建请求
     * @param  [type] $protocolCmd [description]
     * @return [type]              [description]
     */
    public function createRequest($protocolCmd)
    {
        //此处使用反射创建
        if(isset($this->_protocolMappers[$protocolCmd]))
        {
            return new $this->_protocolMappers[$protocolCmd]();
        }

        return null;   
    }

}