<?php
$redis = new \Redis();
$redis->connect('127.0.0.1',6379);
$redis->auth('redis4.0.1');
$redis->set('test','abctest');
echo $redis->get('test') . PHP_EOL;