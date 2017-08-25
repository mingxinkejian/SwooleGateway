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
/**
* 
*/
class WorkerLogic
{
    /**
     * 服务器启动时的回调，可以在此初始化数据库连接等等
     * @return [type] [description]
     */
    public static function onServerStart($server)
    {

    }

    public static function clientConnect()
    {
    }

    public static function clientMessage($msgPkg)
    {
        $gatewayData                    = GatewayWorkerProtocol::$emptyPkg;
        $gatewayData['cmd']             = CmdDefine::CMD_SEND_TO_ONE;
        $gatewayData['connectionId']    = Context::$connectionId;
        $gatewayData['body']            = json_decode($msgPkg,true)['swooleClient'];

        Context::$connection->send($gatewayData);
    }

    public static function clientClose()
    {

    }
}