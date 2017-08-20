<?php 
/**
 * 测试发送包到网关服务器
 */

$testSendPkg = array
(
    'cmd'           =>  0,
    'localIp'       =>  0,
    'localPort'     =>  0,
    'clientIp'      =>  0,
    'clientPort'    =>  0,
    'connectionId'  =>  0,
    'gatewayPort'   =>  0,
    'extData'       =>  '',
    'body'          =>  ''
);


$client = new \swoole_client(SWOOLE_SOCK_TCP);
if(!$client->connect('127.0.0.1', 9501, -1))
{
    exit("connect failed. Error: {$client->errCode}\n");
}
for ($i=0; $i < 10; $i++) { 
    $client->send($pkg1);
    $client->send($pkg2);
    usleep(10);
}
$recvData = $client->recv();
echo 'recv len:' . strlen($recvData) . PHP_EOL;
echo $recvData . PHP_EOL;
$client->close();