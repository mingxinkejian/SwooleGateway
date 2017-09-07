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

use SwooleGateway\Server\Protocols\GatewayWorkerProtocol;
use SwooleGateway\Server\Context\Context;
use SwooleGateway\Common\CmdDefine;
use Logic\LogicManager\WorkerServerManager;
use SwooleGateway\Logger\LoggerLevel;
/**
* 
*/
class WorkerLogic
{
    /**
     * 服务器启动时的回调，可以在此初始化数据库连接等等
     * @return [type] [description]
     */
    public static function onServerStart($workerServer)
    {
        //消息处理注册到管理器中
        WorkerServerManager::getInstance()->registerMsgHandlerMapper();
    }

    public static function clientConnect()
    {

    }

    public static function clientMessage($workerServer,$connection,$msgPkg)
    {
        $protocolCmd = unpack("NprotocolCmd", substr($msgPkg, 0,4));
        $handler = WorkerServerManager::getInstance()->getMsgHandler($protocolCmd['protocolCmd']);
        if(!empty($handler))
        {
            $handler->_server = $workerServer;
            $handler->handlerMsg(Context::$connection, substr($msgPkg, 4));
        }
        else
        {
            Context::$workerServer->_server->logger(LoggerLevel::ERROR,"未找到MsgId:[{$protocolCmd['protocolCmd']}]的MsgHandler");
        }

        // $gatewayData                    = GatewayWorkerProtocol::$emptyPkg;
        // $gatewayData['cmd']             = CmdDefine::CMD_SEND_TO_ONE;
        // $gatewayData['connectionId']    = Context::$connectionId;
        // $gatewayData['body']            = json_decode($msgPkg,true)['swooleClient'];

        // Context::$connection->send($gatewayData);
    }
    
    /**
     * 拆取包头
     * @param  [type] $context [description]
     * @return [type]          [description]
     */
    public static function getClientPkgHeader($context)
    {
        $HEAD_LEN = 22;
        $pkgHeader = unpack("nversion/NappId/ngatewayCmd/NprotocolCmd/ncheckSum/NmsgIdx", substr($context->userData->pkg, 0, $HEAD_LEN));
        $context->userData->pkg = substr($context->userData->pkg, $HEAD_LEN);
        return $pkgHeader;
    }

    public static function clientClose()
    {

    }
}