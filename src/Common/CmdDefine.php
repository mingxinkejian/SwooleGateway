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

namespace SwooleGateway\Common;

/**
* 
*/
class CmdDefine
{
    /**
     * CMD命令01--2FF为系统命令
     */
    /**
     * 心跳包
     */
    const CMD_PING                      = 0x01;
    const CMD_PONG                      = 0x02;

    const CMD_ERROR                     = 0x03;
    //广播网关地址给Worker服务器
    const CMD_BROADCAST_GATEWAYS        = 0x04;
    //网关和Worker组网
    const CMD_REGISTER_REQ_AND_RESP     = 0x05;

    //网关向注册中心注册
    const CMD_GATEWAY_REGISTER_REQ      = 0x100;
    const CMD_GATEWAY_REGISTER_RESP     = 0x101;
    //Worker向注册中心注册
    const CMD_WORKER_REGISTER_REQ       = 0x102;
    const CMD_WORKER_REGISTER_RESP      = 0x103;
    //Worker向网关请求
    const CMD_WORKER_GATEWAY_REQ        = 0x104;
    const CMD_WORKER_GATEWAY_RESP       = 0x105;
    //客户端连接、发送消息、关闭
    const CMD_CLIENT_CONNECTION         = 0x106;
    const CMD_CLIENT_MESSAGE            = 0x107;
    const CMD_CLIENT_CLOSE              = 0x108;

    //网关Cmd
    const CMD_SEND_TO_ONE               = 0x200;
    const CMD_KICK                      = 0x201;
    const CMD_DESTROY                   = 0x202;
    const CMD_SEND_TO_ALL               = 0x203;
    const CMD_SET_SESSION               = 0x204;
    const CMD_UPDATE_SESSION            = 0x205;
    const CMD_GET_SESSION_BY_CLIEND_ID  = 0x206;
    const CMD_GET_ALL_CLIENT_INFO       = 0x207;
    const CMD_IS_ONLINE                 = 0x208;
    const CMD_BIND_UID                  = 0x209;
    const CMD_UNBIND_UID                = 0x20A;
    const CMD_SEND_TO_UID               = 0x20B;
    const CMD_JOIN_GROUP                = 0x20C;
    const CMD_LEAVE_GROUP               = 0x20D;
    const CMD_SEND_TO_GROUP             = 0x20E;
    const CMD_GET_CLIENT_INFO_BY_GROUP  = 0x20F;
    const CMD_GET_CLIENT_COUNT_BY_GROUP = 0x210;
    const CMD_GET_CLIENT_ID_BY_UID      = 0x211;
}