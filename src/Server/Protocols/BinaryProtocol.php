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
* 
*/
class BinaryProtocol implements IProtocol
{
    //默认最大包长度
    const MAX_PACK_LEN = 0x200000; //2M大小
    const PACK_HEAD_LEN = 4;
    public $buffer = '';
    public $dataLength = -1;
    public $headerLength = self::PACK_HEAD_LEN;

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
        $dataLength = strlen($data);

        $headerLength = pack("N", $dataLength);

        return $headerLength . $data;
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

                $context->userData->pkg = $data;

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