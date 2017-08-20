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
namespace SwooleGateway\Server\Connection;

/**
 * 连接信息
 */
abstract class AConnection
{
    /**
     * Id recorder.
     *
     * @var int
     */
    protected static $_idRecorder = 1;
    protected $_id;
    
    public $id;     //此连接的唯一ID
    public $fd;     //用来标记fdId，数组下标可用此做

    public $server;
    public $socket;
    public $fromId;
    public $userData;

    public $protocol;

    function __construct()
    {
        
    }

    public function getConnectionInfo($server = null,$fd = -1)
    {
        //获取客户端连接信息
        if ($server == null) {
            $server = $this->server;
        }
        
        if($fd == -1)
        {
            $fd = $this->fd;
        }
        $swConnInfo = $server->connection_info($fd);
        return $swConnInfo;
    }
}