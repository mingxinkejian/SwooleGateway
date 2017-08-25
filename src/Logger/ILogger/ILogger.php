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
namespace SwooleGateway\Logger\ILogger;

interface ILogger
{
    public function init($config);

    public function debug($msg);

    public function info($msg);

    public function error($msg);

    public function warn($msg);

    public function notice($msg);
}