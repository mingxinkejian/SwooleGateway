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

namespace SwooleGateway\IO;

/**
* 
*/
class FileIO 
{
    public $filePath;
    public $fileContent;
    /**
     * 是否存在目录
     * @param  [type]  $path [description]
     * @return boolean       [description]
     */
    public static function isExistDirectory($path)
    {
        return file_exists($path);
    }
    /**
     * 是否存在文件
     * @param  [type]  $path [description]
     * @return boolean       [description]
     */
    public static function isExistFile($path)
    {
        return file_exists($path);
    }

    public function setWritePath($filePath)
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function writeFile($fileContent)
    {
        $this->fileContent = $fileContent;
        return $this;
    }

    /**
     * 创建文件
     * @param  [boolean] $delBefore [创建之前是否删除]
     * @return [type]            [description]
     */
    public function create($delBefore = true)
    {
        if($delBefore)
        {
            if(self::isExistFile($this->filePath)) 
            {
                $this->delFile();
            }
        }

        file_put_contents($this->filePath, $this->fileContent);
    }

    public function appendFile()
    {
        file_put_contents($this->filePath, $this->fileContent, FILE_APPEND);
    }

    public function delFile()
    {
        return unlink($this->filePath);
    }
}