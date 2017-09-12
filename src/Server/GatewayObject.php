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


namespace SwooleGateway\Server;

/**
* 
*/
class GatewayObject
{
    public $_server;
    public $_settings;
    public $_swSettings;

    public function parseConfig($config,$mode)
    {
        $scheme = parse_url($config['uri']);
        if($scheme)
        {
            switch(strtolower($scheme['scheme']))
            {
                case 'http':
                case 'https':
                    throw new Exception("Can't support this scheme: {$scheme['scheme']}");
                    break;
                case 'ws':
                case 'wss':
                    # code...
                    $this->_server = new \SwooleGateway\Server\WebSocket\WebSocketServer($config, $mode);
                    break;
                case 'tcp':
                case 'tcp4':
                case 'tcp6':
                case 'ssl':
                case 'sslv2':
                case 'sslv3':
                case 'tls':
                case 'unix':
                    $this->_server = new \SwooleGateway\Server\Socket\SocketServer($config, $mode);
                    break;
                default:
                    throw new Exception("Can't support this scheme: {$scheme['scheme']}");
                    break;
            }
        }
        else
        {
            throw new \Exception("Can't parse this url: " . $config['uri']);
        }
    }

    public function start()
    {
        $this->_server->startServer();
    }

    public function closeServer()
    {

    }

    public function loadSwooleConfig($config)
    {
        $this->_swSettings = $config;
        $this->_server->loadSwooleConfig($config);
    }

    /**
     * 分割svrId,
     * svrId类似于IPv4地址
     * 0.0.0.0形式，中间两位为保留位
     * 1.0.0.2表示大区ID为1，服务器ID为2
     * 
     * @param  [type] $svrId [description]
     * @return [type]        [description]
     */
    protected function splitSvrId($svrId)
    {
        return explode('.', $svrId);
    }

    // public function __call($name, $args)
    // {
    //     return call_user_func_array(array($this->_server, $name), $args);
    // }
    // public function __set($name, $value)
    // {
    //     $this->_server->$name = $value;
    // }
    // public function __get($name)
    // {
    //     return $this->_server->$name;
    // }
    // public function __isset($name)
    // {
    //     return isset($this->_server->$name);
    // }
    // public function __unset($name)
    // {
    //     unset($this->_server->$name);
    // }
}