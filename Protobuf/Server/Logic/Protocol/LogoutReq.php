<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: LoginProtocol.proto

namespace Logic\Protocol;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 *登出请求
 *
 * Generated from protobuf message <code>Logic.Protocol.LogoutReq</code>
 */
class LogoutReq extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>int32 uId = 1;</code>
     */
    private $uId = 0;

    public function __construct() {
        \GPBMetadata\LoginProtocol::initOnce();
        parent::__construct();
    }

    /**
     * Generated from protobuf field <code>int32 uId = 1;</code>
     * @return int
     */
    public function getUId()
    {
        return $this->uId;
    }

    /**
     * Generated from protobuf field <code>int32 uId = 1;</code>
     * @param int $var
     * @return $this
     */
    public function setUId($var)
    {
        GPBUtil::checkInt32($var);
        $this->uId = $var;

        return $this;
    }

}

