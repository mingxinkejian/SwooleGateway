<?php

/**
 * @Author: Ming ming
 * @Date:   2017-09-09 16:14:57
 * @Last Modified by:   Ming ming
 * @Last Modified time: 2017-09-18 12:12:59
 */
namespace Logic\MsgHandler\GatewayMsgHandler;

use Logic\LogicManager\MsgHandler;
use SwooleGateway\Server\Protocols\GatewayWorkerProtocol;
use SwooleGateway\Common\CmdDefine;
use SwooleGateway\Server\Context\Context;
use Logic\CommonDefine\CommonDefine;
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
        $this->_protocolMappers[ProtocolCmd::CMD_LOGIN_REQ][1] = MsgHandler::MSG_REQ_NAMESPACE . "LoginReq";
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
        $tokenKeyName = $accountKey . CommonDefine::TOKEN_NAME_SUFFIX;
       
        $userToken = $request->getLoginToken();

        $cacheToken = GatewayServerManager::getInstance()->tokenRedis->get($tokenKeyName);
        $accountData = GatewayServerManager::getInstance()->dbRedis->get($accountKey);

        if($userToken !== $cacheToken)
        {
            $loginResp = new LoginResp();
            $loginResp->setRet(RetCode::LOGIN_FAILED);
            $loginResp->setSvrTime(time());
            $loginResp->setVersion('1.0.0');
            $loginResp->setExtMsg('亲爱的用户，您的登陆Token验证失败，请重试！');
            GatewayServerManager::getInstance()->sendMsgToClient($connection, ProtocolCmd::CMD_LOGIN_RESP, $loginResp->serializeToString());
            return;
        }

        if($accountData === false)
        {
            $loginResp = new LoginResp();
            $loginResp->setRet(RetCode::LOGIN_FAILED);
            $loginResp->setSvrTime(time());
            $loginResp->setVersion('1.0.0');
            $loginResp->setExtMsg('亲爱的用户，您的账号信息验证错误，请重试！');
            GatewayServerManager::getInstance()->sendMsgToClient($connection, ProtocolCmd::CMD_LOGIN_RESP, $loginResp->serializeToString());
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
                    $loginResp->setExtMsg('亲爱的用户，您的登陆密码错误，请重试！');
                    GatewayServerManager::getInstance()->sendMsgToClient($connection, ProtocolCmd::CMD_LOGIN_RESP, $loginResp->serializeToString());
                    return;
                }
            }
        }

        //登陆成功后绑定svrId
        if($this->_server->bindClientToWorker($connection,$request->getSvrId()) == false)
        {
            $loginResp = new LoginResp();
            $loginResp->setRet(RetCode::LOGIN_FAILED);
            $loginResp->setSvrTime(time());
            $loginResp->setVersion('1.0.0');
            $loginResp->setExtMsg('亲爱的用户，服务器处于维护状态，请稍后再试！');
            GatewayServerManager::getInstance()->sendMsgToClient($connection, ProtocolCmd::CMD_LOGIN_RESP, $loginResp->serializeToString());
            return;
        }

        //登陆成功的话，将uId等信息转发给游戏服，游戏服返回登陆信息
        $this->_server->sendClientMsgToWorker(CmdDefine::CMD_CLIENT_MESSAGE, $connection, $connection->userData->gatewayHeader, self::packClientHeader($context->userData->pkgHeader), $accountData);
    }

}