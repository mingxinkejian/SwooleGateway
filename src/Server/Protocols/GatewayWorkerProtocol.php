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

namespace SwooleGateway\Server\Protocols;

use SwooleGateway\Server\Protocols\IProtocol\IProtocol;
/**
* Gateway和Worker通信的通信协议
* struct GatewayProtocol
* {
*     unsigned int          packLen,
*     unsigned int          cmd,//命令字
*     unsigned int          localIp,
*     unsigned short        localPort,
*     unsigned int          clientIp,
*     unsigned short        clientPort,
*     unsigned int          connectionId,
*     unsigned short        gatewayPort,
*     unsigned int          extLen,
*     char[extLen]         extData,
*     char[packLen - HEAD_LEN] body//包体
* }
* NCNnNnNCnN
*/
class GatewayWorkerProtocol implements IProtocol
{
    const HEAD_LEN = 30; //包头信息一共30个字节
    //默认最大包长度
    const MAX_PACK_LEN = 0x200000; //2M大小
    const PACK_HEAD_LEN = 4;
    public $buffer = '';
    public $dataLength = -1;
    public $headerLength = self::PACK_HEAD_LEN;

    public static $emptyPkg = array
    (
        'cmd'           =>  0,
        'localIp'       =>  0,
        'localPort'     =>  0,
        'clientIp'      =>  0,
        'clientPort'    =>  0,
        'connectionId'  =>  0,
        'gatewayPort'   =>  0,
        'extData'       =>  '',
        'body'          =>  ''
    );

    public $onReceivePkg = null;
    public $server;
    public $fromId;
    public $fd;

    public function __construct($server,$fd,$fromId)
    {
        $this->server = $server;
        $this->fromId = $fromId;
        $this->fd = $fd;
    }

    /**
     * 返回包长渡
     * @return [type] [description]
     */
    public function getPkgLen($buffer)
    {
        if(strlen($buffer) < self::HEAD_LEN)
        {
            return 0;
        }

        $data = unpack('NpackLen', $buffer);
        return $data['packLen'];
    }
    /**
     * 序列化数据
     * @return [type] [description]
     */
    public function encode($data)
    {
        $extLen = strlen($data['extData']);
        $packageLen = self::HEAD_LEN + $extLen + strlen($data['body']);

        return pack('NNNnNnNnN', $packageLen,
            $data['cmd'], $data['localIp'], $data['localPort'],
            $data['clientIp'], $data['clientPort'], $data['connectionId'],
            $data['gatewayPort'], $extLen) . $data['extData'] . $data['body'];
    }
    /**
     * 反序列化数据
     * @return [type] [description]
     */
    public function decode($connection,$recvData)
    {
        $this->buffer .= $recvData;
        while(true)
        {
            $length = strlen($this->buffer);
            //拆包 len+body
            if($this->dataLength < 0 && $length >= $this->headerLength)
            {
                $unpackArr = unpack("NdataLen", substr($this->buffer, 0, self::PACK_HEAD_LEN));
                $this->dataLength = $unpackArr['dataLen'];
            }

            if(($this->dataLength >= 0) && ($length - $this->headerLength) >= $this->dataLength)
            {
                $context = new \stdClass();
                $context->server = $this->server;
                $context->socket = $this->fd;
                $context->fd = $this->fd;
                $context->fromId = $this->fromId;
                $context->userData = new \stdClass();

                $data = substr($this->buffer, $this->headerLength, $this->dataLength);
                $this->buffer = substr($this->buffer, $this->headerLength + $this->dataLength);

                $pkg = unpack('NpackLen/Ncmd/NlocalIp/nlocalPort/NclientIp/nclientPort/NconnectionId/ngatewayPort/NextLen', $data);
                if($pkg['extLen'] > 0)
                {
                    $pkg['extData'] = substr($data, self::HEAD_LEN, $pkg['extLen']);
                    $pkg['body'] = substr($data, self::HEAD_LEN + $pkg['extLen']);
                }
                else
                {
                    $pkg['extData'] = '';
                    $pkg['body'] = substr($data, self::HEAD_LEN);
                }

                $context->userData->pkg = $pkg;

                $this->headerLength = self::PACK_HEAD_LEN;
                $this->dataLength = -1;

                try {
                    if(is_callable($this->onReceivePkg))
                    {
                        call_user_func($this->onReceivePkg, $connection, $context);
                    }
                }
                catch (Exception $e) { $this->server->close($this->fd); }
                catch (Throwable $e) { $this->server->close($this->fd); }
            }
            else
            {
                break;
            }
        }
    }
}