<?php
/**
 * 启动网关服务器
 */
if (version_compare(PHP_VERSION, '5.5.0', '<')) {
    die('require PHP > 5.5.0 !');
}

define('RunRoot', __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR);

require __DIR__ . DIRECTORY_SEPARATOR .'..' . DIRECTORY_SEPARATOR . 'AutoLoader.php';
use SwooleGateway\AutoLoader;

AutoLoader::setRootPath(__DIR__ . '/../src');


echo "Starting gatewayServer" . PHP_EOL;

use SwooleGateway\ConfigReader\JsonReader;
use SwooleGateway\Server\WorkerServer;
/**
 * 1、读取配置文件
 * 2、加载配置文件
 * 3、启动
 */

$confPath = $argv[1];

if (empty($confPath)) {
    echo "ConfigPath is null,Please check !";
    return;
}

$config = array();
$swooleConfig = array();

$jsonReader = new JsonReader();
$config = $jsonReader->parseConf($confPath);
if (empty($config)) {
    echo "Config is null,Please check !";
    return;
}

$swooleConfig = $jsonReader->parseConf(RunRoot . $config['swooleConf']);
if (empty($config)) {
    echo "Swoole Config is null,Please check !";
    return;
}

$gateway = new WorkerServer($config);
$gateway->loadSwooleConfig($swooleConfig);
$gateway->start();
