<?php

/**
 * @Author: Ming ming
 * @Date:   2017-09-18 11:59:43
 * @Last Modified by:   Ming ming
 * @Last Modified time: 2017-09-18 12:31:18
 */
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
class ServerLogic
{
    private const CLIENT_HEADER_LEN = 20;
    /**
     *  unsigned int    packLen,        (4字节) //包长度，包括数据字段
     *  AppClientPkgHeader 长度一共20个字节
     *  unsigned short  version,        (2字节) //协议版本号
     *  unsigned int    appId,          (4字节) //应用ID 
     *  unsigned short  gatewayCmd,     (2字节) //网关命令
     *  unsigned int    protocolCmd,    (4字节) //协议命令
     *  unsigned short  subCmd,         (2字节) //小命令字
     *  unsigned short  checkSum,       (2字节) //数据校验
     *  unsigned int    msgIdx          (4字节) //数据包顺序
     */
    public static function getClientPkgHeader($context)
    {
        $pkgHeader = unpack("nversion/NappId/ngatewayCmd/NprotocolCmd/nsubCmd/ncheckSum/NmsgIdx", substr($context->userData->pkg, 0, ServerLogic::CLIENT_HEADER_LEN));
        $context->userData->pkg = substr($context->userData->pkg, ServerLogic::CLIENT_HEADER_LEN);
        return $pkgHeader;
    }
}