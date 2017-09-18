<?php

/**
 * @Author: Ming ming
 * @Date:   2017-09-08 11:31:30
 * @Last Modified by:   Ming ming
 * @Last Modified time: 2017-09-18 12:11:49
 * 
 * 所有消息的基类，MsgHandler文件夹下的所有Handler都继承此类
 * 
 */
namespace Logic\LogicManager;

use Logic\Protocol\LoginType;
use Logic\Protocol\OSType;

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
    public function createRequest($protocolCmd,$subCmd)
    {
        //此处使用反射创建
        if(isset($this->_protocolMappers[$protocolCmd][$subCmd]))
        {
            return new $this->_protocolMappers[$protocolCmd][$subCmd]();
        }

        return null;   
    }

    /**
     * 根据注册信息获取账号数据，前2位为登陆类型和终端类型
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    protected function getAccountKey($request)
    {
        $accountKey = '';
        $LoginType = $request->getLoginType();
        $loginOsType = $request->getOsType();
        switch($request->getLoginType())
        {
            case LoginType::LOGIN_TYPE_ACCOUNT:
                $accountKey = sprintf("account_%02d%02d%s",$LoginType, $loginOsType, $request->getUsername());
                break;
            case LoginType::LOGIN_TYPE_MSDK_QQ:
            case LoginType::LOGIN_TYPE_MSDK_WX:
            case LoginType::LOGIN_TYPE_GUEST:
            case LoginType::LOGIN_TYPE_THIRD_PLATFORM:
                $accountKey = sprintf("account_%02d%02d%s",$LoginType, $loginOsType, $request->getOpenId());
                break;
            default:

                break;
        };
        return $accountKey;
    }

    public static function packClientHeader($headerArray)
    {
        return pack("nNnNnnN",$headerArray['version'], $headerArray['appId'], $headerArray['gatewayCmd'], $headerArray['protocolCmd'], $headerArray['subCmd'], $headerArray['checkSum'], $headerArray['msgIdx']);
    }
}