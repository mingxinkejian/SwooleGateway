<?php 
#
# This file is part of SwooleGateway.
#
# Licensed under The MIT License
# For full copyright and license information, please see the MIT-LICENSE.txt
# Redistributions of files must retain the above copyright notice.
#
# @author    mingming<363658434@qq.com>
# @copyright mingming<363658434@qq.com>
# @link      xxxx
# @license   http://www.opensource.org/licenses/mit-license.php MIT License
#

namespace Logic;

use Logic\ServerLogic;
use SwooleGateway\Server\Protocols\GatewayWorkerProtocol;
use SwooleGateway\Server\Context\Context;
use SwooleGateway\Common\CmdDefine;
use Logic\LogicManager\WorkerServerManager;
use SwooleGateway\Logger\LoggerLevel;
/**
* 
*/
class WorkerLogic extends ServerLogic
{
    /**
     * 服务器启动时的回调，可以在此初始化数据库连接等等
     * @return [type] [description]
     */
    public static function onServerStart($workerServer)
    {
        //消息处理注册到管理器中
        WorkerServerManager::getInstance()->registerMsgHandlerMapper();

        self::initRedis();
    }

    public static function initRedis()
    {
        WorkerServerManager::getInstance()->initRedis($gatewayServer->_settings['redisConf']);
    }

    public static function pingDB()
    {
        WorkerServerManager::getInstance()->pingDB();
    }

    public static function clientConnect()
    {

    }

    public static function clientMessage($workerServer,$connection,$msgPkg)
    {
        try {
            $context = new \stdClass();
            $context->userData = new \stdClass();
            $context->userData->pkg = $msgPkg;
            //拆包
            $clientPkgHeader = self::getClientPkgHeader($context);
            $handler = WorkerServerManager::getInstance()->getMsgHandler($clientPkgHeader['protocolCmd']);
            if(!empty($handler))
            {
                //根据cmd创建Request
                $request = $handler->createRequest($clientPkgHeader['protocolCmd'],$clientPkgHeader['subCmd']);
                if(isset($request))
                {
                    $context->userData->pkgHeader = $clientPkgHeader;
                    $handler->_server = $workerServer;
                    $handler->handlerMsg(Context::$connection, $request, $context);
                }
                else
                {
                    $workerServer->_server->logger(LoggerLevel::ERROR, "未找到protocolCmd:[{$clientPkgHeader['protocolCmd']}] subCmd:[{$clientPkgHeader['subCmd']}]  的Request");
                }
                
                
            }
            else
            {
                Context::$workerServer->_server->logger(LoggerLevel::ERROR, "未找到protocolCmd:[{$clientPkgHeader['protocolCmd']}]的MsgHandler");
            }
        }
        catch(Exception $e)
        {
            //断开连接
            $workerServer->_server->logger(LoggerLevel::ERROR, $e->getMessage());
        }

        // $gatewayData                    = GatewayWorkerProtocol::$emptyPkg;
        // $gatewayData['cmd']             = CmdDefine::CMD_SEND_TO_ONE;
        // $gatewayData['connectionId']    = Context::$connectionId;
        // $gatewayData['body']            = json_decode($msgPkg,true)['swooleClient'];

        // Context::$connection->send($gatewayData);
    }
    
    public static function clientClose()
    {

    }
}