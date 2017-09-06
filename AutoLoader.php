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

namespace SwooleGateway;

/**
* 
*/
class AutoLoader
{
    /**
     * Autoload root path.
     *
     * @var string
     */
    protected static $_autoloadRootPath = '';

    /**
     * Set autoload root path.
     *
     * @param string $root_path
     * @return void
     */
    public static function setRootPath($root_path)
    {
        self::$_autoloadRootPath = $root_path;
    }

    /**
     * Load files by namespace.
     * 此处自动加载添加了src目录
     * 
     * @param string $name
     * @return boolean
     */
    public static function loadByNamespace($name)
    {
        $class_path = str_replace('\\', DIRECTORY_SEPARATOR, $name);
        if(strpos($name, 'SwooleGateway\\') === 0)
        {
            $class_file = __DIR__ . DIRECTORY_SEPARATOR . 'src' . substr($class_path, strlen('SwooleGateway')) . '.php';
        }
        else if(strpos($name, 'Logic\\') === 0)
        {
            $class_file = __DIR__ . DIRECTORY_SEPARATOR . 'logic' . substr($class_path, strlen('Logic')) . '.php';
        }
        else if(strpos($name, 'GPBMetadata\\') === 0)
        {
            //此处是为了处理Protobuf协议的
            $class_file = __DIR__ . DIRECTORY_SEPARATOR . 'logic' . DIRECTORY_SEPARATOR . 'Protocol' . DIRECTORY_SEPARATOR . $class_path . '.php';
        }
        else
        {
            if(self::$_autoloadRootPath)
            {
                $class_file = self::$_autoloadRootPath . DIRECTORY_SEPARATOR . $class_path . '.php';
            }
            if(empty($class_file) || !is_file($class_file))
            {
                $class_file = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . "$class_path.php";
            }
        }
        if(is_file($class_file))
        {
            require_once($class_file);
            if(class_exists($name, false))
            {
                return true;
            }
        }
        return false;
    }
}
spl_autoload_register('\SwooleGateway\Autoloader::loadByNamespace');