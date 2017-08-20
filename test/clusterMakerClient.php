<?php
$client = new swoole_client(SWOOLE_SOCK_UDP, SWOOLE_SOCK_SYNC);
$client->connect('127.0.0.1', 9999);
$client->send(json_encode(['hello' => str_repeat('A', 600), 'rand' => rand(1, 100)]));
echo $client->recv() . "\n";
sleep(1);
