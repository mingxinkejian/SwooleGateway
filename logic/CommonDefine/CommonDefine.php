<?php

namespace Logic\CommonDefine;

class CommonDefine
{
    const UID_INIT                  = 1000000;   //默认UID的起始值
    const UID_INCR_STEP             = 1;    //自增步长
    const REDIS_KEY_REG_SEQUENCE    = "REDIS_KEY_REG_SEQUENCE";
    const LOGIN_TOKEN_EXPIRE_TIME   = 60;   //登陆Token过期时间
}