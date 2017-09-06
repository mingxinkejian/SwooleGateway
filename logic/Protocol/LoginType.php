<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: LoginProtocol.proto

namespace Logic\Protocol;

/**
 *玩家登陆类型
 *
 * Protobuf enum <code>Logic\Protocol\LoginType</code>
 */
class LoginType
{
    /**
     *默认不使用
     *
     * Generated from protobuf enum <code>LOGIN_TYPE_DEFAULT = 0;</code>
     */
    const LOGIN_TYPE_DEFAULT = 0;
    /**
     *QQ平台
     *
     * Generated from protobuf enum <code>LOGIN_TYPE_MSDK_QQ = 1;</code>
     */
    const LOGIN_TYPE_MSDK_QQ = 1;
    /**
     *微信平台
     *
     * Generated from protobuf enum <code>LOGIN_TYPE_MSDK_WX = 2;</code>
     */
    const LOGIN_TYPE_MSDK_WX = 2;
    /**
     *游客访问
     *
     * Generated from protobuf enum <code>LOGIN_TYPE_MSDK_GUEST = 3;</code>
     */
    const LOGIN_TYPE_MSDK_GUEST = 3;
    /**
     *第三方平台
     *
     * Generated from protobuf enum <code>LOGIN_TYPE_THIRD_PLATFORM = 4;</code>
     */
    const LOGIN_TYPE_THIRD_PLATFORM = 4;
}
