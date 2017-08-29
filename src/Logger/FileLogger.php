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

use SwooleGateway\Logger\ILogger\ILogger;
/**
 * 
 * 使用.=符号来拼接字符串效率最高
 * 此处队列性能也很高
 * 测试数据100w条数据，从入队到拼接字符串到出队一共耗时0.242539 s
 * 测试环境：
 * PHP:     PHP7.1.4
 * Memory:  12GB
 * CPU:     2.5 GHz Intel Core i5
 * OS:      MacOs 10.12.5
 * 固态硬盘
 */
class FileLogger implements ILogger
{
    private $_baseLogPath = '';
    private $_isCache = false;
    
    private $_cacheDebugQueue;
    private $_cacheInfoQueue;
    private $_cacheErrorQueue;
    private $_cacheWarnQueue;
    private $_cacheNoticeQueue;

    private $_cacheQueueMax = 0;
    private $_logLevel = 1;

    private $_debugPath = 'debug';
    private $_infoPath = 'info';
    private $_errorPath = 'error';
    private $_warnPath = 'warn';
    private $_noticePath = 'notice';


    public function init($config)
    {
        $this->_baseLogPath = $config['logConfig']['logPath'];

        $this->_isCache = $config['logConfig']['isCache'];
        $this->_logLevel = $config['logConfig']['logLevel'];
        $this->_cacheQueueMax = $config['logConfig']['queueMax'];
        $this->checkAllDir();

        $this->_cacheDebugQueue     = new \SplQueue();
        $this->_cacheInfoQueue      = new \SplQueue();
        $this->_cacheErrorQueue     = new \SplQueue();
        $this->_cacheWarnQueue      = new \SplQueue();
        $this->_cacheNoticeQueue    = new \SplQueue();

        ServerException::initException($this);
    }

    private function checkAllDir()
    {
        //是否存在debug的文件夹
        $this->_debugPath = $this->_baseLogPath . DIRECTORY_SEPARATOR . $this->_debugPath;
        if(!file_exists($this->_debugPath))
        {
            mkdir($this->_debugPath,0777,true);
        }
        //是否存在info的文件夹
        $this->_infoPath = $this->_baseLogPath . DIRECTORY_SEPARATOR . $this->_infoPath;
        if(!file_exists($this->_infoPath))
        {
            mkdir($this->_infoPath,0777,true);
        }
        //是否存在error的文件夹
        $this->_errorPath = $this->_baseLogPath . DIRECTORY_SEPARATOR . $this->_errorPath;
        if(!file_exists($this->_errorPath))
        {
            mkdir($this->_errorPath,0777,true);
        }
        //是否存在warn的文件夹
        $this->_warnPath = $this->_baseLogPath . DIRECTORY_SEPARATOR . $this->_warnPath;
        if(!file_exists($this->_warnPath))
        {
            mkdir($this->_warnPath,0777,true);
        }
        //是否存在notice的文件夹
        $this->_noticePath = $this->_baseLogPath . DIRECTORY_SEPARATOR . $this->_noticePath;
        if(!file_exists($this->_noticePath))
        {
            mkdir($this->_noticePath,0777,true);
        }
    }

    public function debug($msg)
    {
        if($this->_logLevel < LoggerLevel::DEBUG)
        {
            return;
        }
        $destination = $this->_debugPath . DIRECTORY_SEPARATOR . date( 'y_m_d_H' ) . '.log';
        $logMsg = '[DEBUG]' . date('Y-M-d H:i:s') . ' ' . $msg .PHP_EOL;
        if($this->_isCache)
        {
            $this->cacheWriteLog($this->_cacheDebugQueue, $this->_debugPath, $destination, $logMsg);
            return;
        }
        $this->checkDir($this->_debugPath);
        $this->writeLog($destination,$logMsg);
    }

    public function info($msg)
    {
        if($this->_logLevel < LoggerLevel::INFO)
        {
            return;
        }
        $destination = $this->_infoPath . DIRECTORY_SEPARATOR . date( 'y_m_d_H' ) . '.log';
        $logMsg = '[INFO]' . date('Y-M-d H:i:s') . ' ' . $msg . PHP_EOL;
        if($this->_isCache)
        {
            $this->cacheWriteLog($this->_cacheInfoQueue, $this->_infoPath, $destination, $logMsg);
            return;
        }
        $this->checkDir($this->_infoPath);
        $this->writeLog($destination,$logMsg);
    }

    public function error($msg)
    {
        if($this->_logLevel < LoggerLevel::ERROR)
        {
            return;
        }
        $destination = $this->_errorPath . DIRECTORY_SEPARATOR . date( 'y_m_d_H' ) . '.log';
        $logMsg = '[ERROR]' . date('Y-M-d H:i:s') . ' ' . $msg . PHP_EOL;
        if($this->_isCache)
        {
            $this->cacheWriteLog($this->_cacheErrorQueue, $this->_errorPath, $destination, $logMsg);
            return;
        }
        $this->checkDir($this->_errorPath);
        $this->writeLog($destination,$logMsg);
    }

    public function warn($msg)
    {
        if($this->_logLevel < LoggerLevel::WARN)
        {
            return;
        }
        $destination = $this->_warnPath . DIRECTORY_SEPARATOR . date( 'y_m_d_H' ) . '.log';
        $logMsg = '[WARN]' . date('Y-M-d H:i:s') . ' ' . $msg . PHP_EOL;
        if($this->_isCache)
        {
            $this->cacheWriteLog($this->_cacheWarnQueue, $this->_warnPath, $destination, $logMsg);
            return;
        }
        $this->checkDir($this->_warnPath);
        $this->writeLog($destination,$logMsg);
    }

    public function notice($msg)
    {
        if($this->_logLevel < LoggerLevel::NOTICE)
        {
            return;
        }
        $destination = $this->_noticePath . DIRECTORY_SEPARATOR . date( 'y_m_d_H' ) . '.log';
        $logMsg = '[NOTICE]' . date('Y-M-d H:i:s') . ' ' . $msg . PHP_EOL;
        if($this->_isCache)
        {
            $this->cacheWriteLog($this->_cacheNoticeQueue, $this->_noticePath, $destination, $logMsg);
            return;
        }
        $this->checkDir($this->_noticePath);
        $this->writeLog($destination,$logMsg);
    }

    private function writeLog($path,$msg)
    {
        error_log($msg, 3, $path);
    }

    public function flushAllLog()
    {

    }

    private function cacheWriteLog($queue,$logPath,$destination,$logMsg)
    {
        $queue->enqueue($logMsg);
        if($queue->count() >= $this->_cacheQueueMax)
        {
            $logAllMsg = '';
            $queue->rewind();
            while($queue->valid())
            {
                echo $queue->current() , PHP_EOL;
                $logAllMsg .= $queue->current();
                echo $logAllMsg;
                $queue->next();
                $queue->dequeue();
            }
            if(!empty($logAllMsg))
            {
                $this->checkDir($logPath);
                $this->writeLog($destination,$logAllMsg);
            }
        }
    }

    private function checkDir($dir)
    {
        if(!file_exists($dir))
        {
            mkdir($dir,0777,true);
        }
    }

    // private function getTrace()
    // {
    //     $info = '';
    //     $file = '';
    //     $func = '';
    //     $class = '';
    //     $line = 0;
    //     $trace = debug_backtrace();
    //     if(isset($trace[2]))
    //     {
    //         $file = $trace[1]['file'];
    //         $func = $trace[2]['function'];
    //         if((substr($func, 0, 7) == 'include') || (substr($func, 0, 7) == 'require'))
    //         {
    //             $func = '';
    //         }
    //         $line = $trace[2]['line'];
    //     }else if (isset($trace[1]))
    //     {
    //         $file = $trace[1]['file'];
    //         $func = '';
    //         $line = $trace[1]['line'];
    //     }
    //     if(isset($trace[3]['class']))
    //     {
    //         $class = $trace[3]['class'];
    //         $func = $trace[3]['function'];
    //         $file = $trace[2]['file'];
    //         $line = $trace[2]['line'];
    //     }else if (isset($trace[2]['class']))
    //     {
    //         $class = $trace[2]['class'];
    //         $func = $trace[2]['function'];
    //         $file = $trace[1]['file'];
    //         $line = $trace[2]['line'];
    //     }
    //     if($file != '')
    //     {
    //         $file = basename($file);
    //     }

    //     $info = ' [' . $file . ' Line: ' . $line . '] ';
    //     return $info;
    // }
}