<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: User.proto

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>UserInfo</code>
 */
class UserInfo extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>int32 id = 1;</code>
     */
    private $id = 0;
    /**
     * Generated from protobuf field <code>int32 test = 2;</code>
     */
    private $test = 0;
    /**
     * Generated from protobuf field <code>string name = 3;</code>
     */
    private $name = '';

    public function __construct() {
        \GPBMetadata\User::initOnce();
        parent::__construct();
    }

    /**
     * Generated from protobuf field <code>int32 id = 1;</code>
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Generated from protobuf field <code>int32 id = 1;</code>
     * @param int $var
     * @return $this
     */
    public function setId($var)
    {
        GPBUtil::checkInt32($var);
        $this->id = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>int32 test = 2;</code>
     * @return int
     */
    public function getTest()
    {
        return $this->test;
    }

    /**
     * Generated from protobuf field <code>int32 test = 2;</code>
     * @param int $var
     * @return $this
     */
    public function setTest($var)
    {
        GPBUtil::checkInt32($var);
        $this->test = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>string name = 3;</code>
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Generated from protobuf field <code>string name = 3;</code>
     * @param string $var
     * @return $this
     */
    public function setName($var)
    {
        GPBUtil::checkString($var, True);
        $this->name = $var;

        return $this;
    }

}

