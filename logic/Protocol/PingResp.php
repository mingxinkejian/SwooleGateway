<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: LoginProtocol.proto

namespace Logic\Protocol;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>Logic.Protocol.PingResp</code>
 */
class PingResp extends \Google\Protobuf\Internal\Message
{
    /**
     *返回值
     *
     * Generated from protobuf field <code>int32 ret = 1;</code>
     */
    private $ret = 0;
    /**
     *服务器时间
     *
     * Generated from protobuf field <code>uint64 svrTime = 2;</code>
     */
    private $svrTime = 0;
    /**
     *扩展信息
     *
     * Generated from protobuf field <code>bytes extData = 3;</code>
     */
    private $extData = '';

    public function __construct() {
        \GPBMetadata\LoginProtocol::initOnce();
        parent::__construct();
    }

    /**
     *返回值
     *
     * Generated from protobuf field <code>int32 ret = 1;</code>
     * @return int
     */
    public function getRet()
    {
        return $this->ret;
    }

    /**
     *返回值
     *
     * Generated from protobuf field <code>int32 ret = 1;</code>
     * @param int $var
     * @return $this
     */
    public function setRet($var)
    {
        GPBUtil::checkInt32($var);
        $this->ret = $var;

        return $this;
    }

    /**
     *服务器时间
     *
     * Generated from protobuf field <code>uint64 svrTime = 2;</code>
     * @return int|string
     */
    public function getSvrTime()
    {
        return $this->svrTime;
    }

    /**
     *服务器时间
     *
     * Generated from protobuf field <code>uint64 svrTime = 2;</code>
     * @param int|string $var
     * @return $this
     */
    public function setSvrTime($var)
    {
        GPBUtil::checkUint64($var);
        $this->svrTime = $var;

        return $this;
    }

    /**
     *扩展信息
     *
     * Generated from protobuf field <code>bytes extData = 3;</code>
     * @return string
     */
    public function getExtData()
    {
        return $this->extData;
    }

    /**
     *扩展信息
     *
     * Generated from protobuf field <code>bytes extData = 3;</code>
     * @param string $var
     * @return $this
     */
    public function setExtData($var)
    {
        GPBUtil::checkString($var, False);
        $this->extData = $var;

        return $this;
    }

}

