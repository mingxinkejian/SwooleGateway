<?php

/**
 * @Author: Ming ming
 * @Date:   2017-09-09 16:14:57
 * @Last Modified by:   Ming ming
 * @Last Modified time: 2017-09-09 16:57:03
 */
namespace Logic\MsgHandler\GatewayMsgHandler;

use Logic\LogicManager\MsgHandler;
use SwooleGateway\Server\Protocols\GatewayWorkerProtocol;
use SwooleGateway\Common\CmdDefine;
use SwooleGateway\Server\Context\Context;
use Logic\LogicManager\GatewayServerManager;

use Logic\Protocol\ProtocolCmd;
use Logic\Protocol\LoginReq;
use Logic\Protocol\LoginResp;
use Logic\Protocol\RetCode;
use Logic\Protocol\LoginType;
use Logic\Protocol\AccountInfo;
/**
 * 登录验证
 */
class AuthLoginMsgHandler extends MsgHandler
{
    public function registProtocols()
    {
        $this->_protocolMappers[ProtocolCmd::CMD_LOGIN_REQ] = MsgHandler::MSG_REQ_NAMESPACE . "LoginReq";
    }

    public function handlerMsg($connection,$request,$context)
    {
        $clientHeader = $context->userData->pkgHeader;
        switch($clientHeader['protocolCmd'])
        {
            case ProtocolCmd::CMD_LOGIN_REQ:
                $this->authLogin($connection, $request, $context);
                break;
            
            default:
                
                break;
        }
    }

    private function authLogin($connection,$request,$context)
    {
        //反序列化
        $request->mergeFromString($context->userData->pkg);

        $accountKey = $this->getAccountKey($request);
        $userToken = GatewayServerManager::getInstance()->tokenRedis->get($request->getLoginToken());
        $accountData = GatewayServerManager::getInstance()->dbRedis->get($accountKey);

        if($userToken === false || $accountData === false)
        {
            $loginResp = new LoginResp();
            $loginResp->setRet(RetCode::LOGIN_FAILED);
            $loginResp->setSvrTime(time());
            $loginResp->setVersion('1.0.0');
            $loginResp->setExtMsg('亲爱的用户，您的登陆验证失败，请重试！');
            GatewayServerManager::getInstance()->sendMsgToClient($connection,ProtocolCmd::CMD_LOGIN_RESP,$loginResp->serializeToString());
            return;
        }

        if($request->getLoginType() == LoginType::LOGIN_TYPE_ACCOUNT)
        {
            //账号登陆的使用密码校验
            $accountInfo = new AccountInfo();
            $accountInfo->mergeFromString($accountData);

            if($request->getUsername() === $accountInfo->getUsername())
            {
                $md5Password = md5($request->getPassword());
                if($md5Password !== $accountInfo->getPassword())
                {
                    $loginResp = new LoginResp();
                    $loginResp->setRet(RetCode::LOGIN_FAILED);
                    $loginResp->setSvrTime(time());
                    $loginResp->setVersion('1.0.0');
                    $loginResp->setExtMsg('亲爱的用户，您的登陆密码，请重试！');
                    GatewayServerManager::getInstance()->sendMsgToClient($connection,ProtocolCmd::CMD_LOGIN_RESP,$loginResp->serializeToString());
                    return;
                }
            }
        }

        //登陆成功的话，将uId等信息转发给游戏服，游戏服返回登陆信息
        $this->_server->sendClientMsgToWorker(CmdDefine::CMD_CLIENT_MESSAGE,$connection,$connection->userData->gatewayHeader,pack("N",ProtocolCmd::CMD_LOGIN_REQ),$accountData);
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