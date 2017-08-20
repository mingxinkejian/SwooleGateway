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
 * Xml配置文件解析
 */
class XmlReader implements IConfigReader
{

    public function parseConf($confFilePath)
    {

        if(is_file($confFilePath))
        {
            $content = simplexml_load_file($confFilePath);
        }else
        {
            $content = simplexml_load_string($confFilePath);
        }
        $result = (array)$content;
        foreach($result as $key=>$val)
        {
            if(is_object($val))
            {
                $result[$key] = (array)$val;
            }
        }
        return $result;
    }
}

