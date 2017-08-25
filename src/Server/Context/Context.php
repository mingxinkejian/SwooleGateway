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

namespace SwooleGateway\Server\Context;

/**
* 
*/
class Context
{
    /**
     * workerServer
     * @var [type]
     */
    public static $workerServer;
    /**
     * 内部通信IP
     * @var [type]
     */
    public static $localIp;
    /**
     * 内部通信端口
     * @var [type]
     */
    public static $localPort;
    /**
     * 客户端IP
     * @var [type]
     */
    public static $clientIp;
    /**
     * 客户端端口
     * @var [type]
     */
    public static $clientPort;
    /**
     * 客户端ID
     * @var [type]
     */
    public static $clientId;
    /**
     * 连接Id
     * @var [type]
     */
    public static $connectionId;

    public static $connection;

    public static function clearContext()
    {
        self::$workerServer = self::$localIp = self::$localPort = self::$clientIp = self::$clientPort = self::$clientId = self::$connectionId = self::$connection = null;
    }

    /**
     * 通讯地址到 client_id 的转换
     * @param  [type] $localIp      [description]
     * @param  [type] $localPort    [description]
     * @param  [type] $connectionId [description]
     * @return [type]               [description]
     */
    public static function addressToClientId($localIp, $localPort, $connectionId)
    {
        return bin2hex(pack('NnN', $localIp, $localPort, $connectionId));
    }
    /**
     * clientId 到通讯地址的转换
     * @param  [type] $clientId [description]
     * @return [type]           [description]
     */
    public static function clientIdToAddress($clientId)
    {
        if (strlen($clientId) !== 20) {
            echo new \Exception("clientId $clientId is invalid");
            return false;
        }
        return unpack('NlocalIp/nlocalPort/NconnectionId', pack('H*', $clientId));
    }
}