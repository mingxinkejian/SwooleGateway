<?php

namespace Logic\MsgHandler\GatewayMsgHandler;

use Logic\LogicManager\MsgHandler;
use SwooleGateway\Server\Protocols\GatewayWorkerProtocol;
use SwooleGateway\Common\CmdDefine;
use SwooleGateway\Server\Context\Context;
use Logic\LogicManager\GatewayServerManager;
use Logic\CommonDefine\CommonDefine;

use Logic\Protocol\ProtocolCmd;
use Logic\Protocol\RegistReq;
use Logic\Protocol\RegistResp;
use Logic\Protocol\LoginType;
use Logic\Protocol\OSType;
use Logic\Protocol\PlayerEnvInfo;
use Logic\Protocol\RetCode;
use Logic\Protocol\AccountInfo;

/**
 * 注册验证
 */
class RegistMsgHandler extends MsgHandler
{
    public function registProtocols()
    {
       $this->_protocolMappers[ProtocolCmd::CMD_REGIST_REQ] = MsgHandler::MSG_REQ_NAMESPACE . "RegistReq";
    }

    public function handlerMsg($connection,$request,$context)
    {
        $clientHeader = $context->userData->pkgHeader;
        switch($clientHeader['protocolCmd'])
        {
            case ProtocolCmd::CMD_REGIST_REQ:
                $this->registUser($connection, $request, $context);
                break;
            
            default:
                
                break;
        }
    }

    private function registUser($connection,$request,$context)
    {
        //反序列化
        $request->mergeFromString($context->userData->pkg);

        /**
         * 注册步骤
         * 判断当前玩家登陆类型
         * 判断数据库中是否存在用户
         * 不存在的话创建用户
         */
        $accountKey = $this->getAccountKey($request);
        if(empty($accountKey))
        {
            $resp = new RegistResp();
            $resp->setRet(RetCode::REGIST_FAILED);
            $resp->setUId(0);
            $resp->setLoginToken('');
            GatewayServerManager::getInstance()->sendMsgToClient($connection,ProtocolCmd::CMD_REGIST_RESP,$resp->serializeToString());
            return;
        }
        else
        {
            $userInfo = GatewayServerManager::getInstance()->redisManager->get($accountKey);

            if($userInfo === false)
            {
                $uId = GatewayServerManager::getInstance()->redisManager->incrBy(CommonDefine::REDIS_KEY_REG_SEQUENCE, CommonDefine::UID_INCR_STEP);
                //添加新用户
                $accountInfo = new AccountInfo();
                $accountInfo->setUId($uId);
                $accountInfo->setUsername($request->getUsername());
                $accountInfo->setPassword(md5($request->getPassword()));
                $accountInfo->setOpenId($request->getOpenId());
                $accountInfo->setLoginType($request->getLoginType());
                $accountInfo->setOsType($request->getOsType());
                $accountInfo->setChannel($request->getChannel());
                $accountInfo->setRegistTime(time());
                
                $ret = GatewayServerManager::getInstance()->redisManager->set($accountKey,$accountInfo->serializeToString());
                if($ret == true)
                {
                    $loginToken = md5($accountKey . microtime());
                    $resp = new RegistResp();
                    $resp->setRet(RetCode::SUCCESS);
                    $resp->setUId($uId);
                    $resp->setLoginToken($loginToken);
                    GatewayServerManager::getInstance()->sendMsgToClient($connection,ProtocolCmd::CMD_REGIST_RESP,$resp->serializeToString());
                    //token可以存到制定的地方
                    //
                    return;
                }
            }
            $resp = new RegistResp();
            $resp->setRet(RetCode::REGIST_FAILED);
            $resp->setUId(0);
            $resp->setLoginToken('');
            GatewayServerManager::getInstance()->sendMsgToClient($connection,ProtocolCmd::CMD_REGIST_RESP,$resp->serializeToString());
            
        }
    }

    /**
     * 根据注册信息获取账号数据，前2位为登陆类型和终端类型
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    private function getAccountKey($request)
    {
        $accountKey = '';
        switch($request->getLoginType())
        {
            case LoginType::LOGIN_TYPE_ACCOUNT:
                $accountKey = sprintf("%01d%01d%s",$request->getLoginType(), $request->getOsType(), $request->getUsername());
                break;
            case LoginType::LOGIN_TYPE_MSDK_QQ:
            case LoginType::LOGIN_TYPE_MSDK_WX:
                
            case LoginType::LOGIN_TYPE_GUEST:
            case LoginType::LOGIN_TYPE_THIRD_PLATFORM:
                $accountKey = sprintf("%01d%01d%s",$request->getLoginType(), $request->getOsType(), $request->getOpenId());
                break;
            default:

                break;
        };
        return $accountKey;
    }
}