<?php
/**
 * 登录服务器
 */

$svrCmd = pack("n",0x150);
$cmd = pack("N",0x1000);
$sendData['userName'] = 'mingtingjian';
$sendData['password'] = 'swooleTest';

$packSendData = msgpack_pack($sendData);

$sendPkg = $svrCmd . $cmd . $packSendData;
$sendPkg = pack("N", strlen($sendPkg)) . $sendPkg;
$client = new \swoole_client(SWOOLE_SOCK_TCP);
if(!$client->connect('127.0.0.1', 9501, -1))
{
    exit("connect failed. Error: {$client->errCode}\n");
}
// for ($i=0; $i < 10; $i++) { 
//     $client->send($pkg1);
    $client->send($sendPkg);
    usleep(1000);
// }
$recvData = $client->recv();
echo 'recv len:' . strlen($recvData) . PHP_EOL;
echo 'recv data:' . $recvData . PHP_EOL;
var_dump(msgpack_unpack(substr($recvData, 4)));
$client->close();
