<?php
/**
 * 测试发送包到网关服务器
 */

$data2 = '{"swooleClient":"测试发送包到网关服务器"}';

echo 'send data len:' . strlen($data2) . PHP_EOL;
$pkg2 = pack("N",strlen($data2)) . $data2;
echo 'send len:' . strlen($pkg2) . PHP_EOL;

$client = new \swoole_client(SWOOLE_SOCK_TCP);
if(!$client->connect('127.0.0.1', 9501, -1))
{
    exit("connect failed. Error: {$client->errCode}\n");
}
// for ($i=0; $i < 10; $i++) { 
//     $client->send($pkg1);
    $client->send($pkg2);
    usleep(1000);
// }
$recvData = $client->recv();
echo 'recv len:' . strlen($recvData) . PHP_EOL;
echo substr($recvData, 4) . PHP_EOL;
$client->close();