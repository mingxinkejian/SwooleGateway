<?php
$data2 = pack("N",1000) . '{"swooleClient":"测试发送包到网关服务器"}';

echo 'send data len:' . strlen($data2) . PHP_EOL;
$pkg2 = pack("N",strlen($data2)) . $data2;

// var_dump(unpack("NmsgId", $pack));

var_dump(bin2hex($pkg2));