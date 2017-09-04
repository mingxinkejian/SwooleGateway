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
namespace SwooleGateway\Logger;

class ServerException extends \Exception{
    
    private static $_logger;

    public static function initException($logger)
    {
        self::$_logger = $logger;
        register_shutdown_function('SwooleGateway\Logger\ServerException::fatalError');
        set_error_handler('SwooleGateway\Logger\ServerException::appError');
        set_exception_handler('SwooleGateway\Logger\ServerException::appException');        
    }
    
    public static function fatalError()
    {        
        $e = error_get_last();
        if(!empty($e))
        {
            switch($e['type'])
            {
                case E_ERROR:
                case E_PARSE:
                case E_CORE_ERROR:
                case E_COMPILE_ERROR:
                case E_USER_ERROR:
                    ob_end_clean();
                    break;
            }
            //保存日志
            $message = "[File : {$e['file']} Line : {$e['line']}] {$e['message']}";
            self::$_logger->error($message);
        }
    }
    
    
    public static function appError($errno, $errstr, $errfile, $errline)
    {
        $errorStr = "[{$errno}] {$errstr} {$errfile} on {$errline} line.";
        switch($errno)
        {
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                self::$_logger->error($errorStr);
                break;
            case E_STRICT:
            case E_USER_WARNING:
            case E_USER_NOTICE:
            default:
                self::$_logger->info($errorStr);
                break;
        }
    }
    
    public static function appException($e)
    {
        // 记录异常日志
        $message = "[File : {$e->getFile()} Line : {$e->getLine()}] Message : {$e->getMessage()} \n Trace : {$e->getTraceAsString()}";
        self::$_logger->error($message);
    }
}