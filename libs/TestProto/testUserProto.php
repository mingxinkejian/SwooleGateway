<?php  
require(__DIR__.DIRECTORY_SEPARATOR.'UserInfo.php');
require(__DIR__.DIRECTORY_SEPARATOR.'GPBMetaData/User.php');
$pbUserInfo = new UserInfo();
$pbUserInfo->setId(1);
$pbUserInfo->setName('echo');
$str = $pbUserInfo->serializeToString();
// $str = $pbUserInfo->serializeToJsonString();
var_dump(bin2hex($str));
$ptTempUser = new UserInfo();
$ptTempUser->mergeFromString($str);
// $ptTempUser->mergeFromJsonString($str);
var_dump("id:".$ptTempUser->getId()." name:".$ptTempUser->getName());