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

namespace SwooleGateway\Client;

use SwooleGateway\Client\IClient\IClient;
/**
* 
*/
class SocketClient extends IClient
{
    public $swClient;
    protected $_swSettings;

    protected $_settings;

    public $onConnect;
    public $onReceive;
    public $onClose;
    public $onError;
    public $onRecv;

    public __construct($config,$mode = SWOOLE_SOCK_TCP,$connectKey = SWOOLE_SOCK_ASYNC)
    {

    }
}