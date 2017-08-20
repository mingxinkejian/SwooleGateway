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

namespace SwooleGateway\ConfigReader;

use SwooleGateway\ConfigReader\IConfig\IConfigReader;
/*
 * Json配置文件解析
 */
class JsonReader implements IConfigReader
{

    public function parseConf($confFilePath)
    {

        if(is_file($confFilePath))
        {
            //读取文件解析
            $jsonStr=file_get_contents($confFilePath);
            
            return json_decode($jsonStr,true);
        }else
        {
            return json_decode($confFilePath,true);
        }
    }
}
