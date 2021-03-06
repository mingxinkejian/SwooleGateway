<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: PlayerProtocol.proto

namespace Logic\Protocol;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 *玩家信息
 *
 * Generated from protobuf message <code>Logic.Protocol.PlayerInfo</code>
 */
class PlayerInfo extends \Google\Protobuf\Internal\Message
{
    /**
     *uId
     *
     * Generated from protobuf field <code>uint32 uId = 1;</code>
     */
    private $uId = 0;
    /**
     *基本信息
     *
     * Generated from protobuf field <code>.Logic.Protocol.PlayerBasicInfo playerBasicInfo = 2;</code>
     */
    private $playerBasicInfo = null;
    /**
     *状态
     *
     * Generated from protobuf field <code>.Logic.Protocol.PlayerState state = 3;</code>
     */
    private $state = 0;

    public function __construct() {
        \GPBMetadata\PlayerProtocol::initOnce();
        parent::__construct();
    }

    /**
     *uId
     *
     * Generated from protobuf field <code>uint32 uId = 1;</code>
     * @return int
     */
    public function getUId()
    {
        return $this->uId;
    }

    /**
     *uId
     *
     * Generated from protobuf field <code>uint32 uId = 1;</code>
     * @param int $var
     * @return $this
     */
    public function setUId($var)
    {
        GPBUtil::checkUint32($var);
        $this->uId = $var;

        return $this;
    }

    /**
     *基本信息
     *
     * Generated from protobuf field <code>.Logic.Protocol.PlayerBasicInfo playerBasicInfo = 2;</code>
     * @return \Logic\Protocol\PlayerBasicInfo
     */
    public function getPlayerBasicInfo()
    {
        return $this->playerBasicInfo;
    }

    /**
     *基本信息
     *
     * Generated from protobuf field <code>.Logic.Protocol.PlayerBasicInfo playerBasicInfo = 2;</code>
     * @param \Logic\Protocol\PlayerBasicInfo $var
     * @return $this
     */
    public function setPlayerBasicInfo($var)
    {
        GPBUtil::checkMessage($var, \Logic\Protocol\PlayerBasicInfo::class);
        $this->playerBasicInfo = $var;

        return $this;
    }

    /**
     *状态
     *
     * Generated from protobuf field <code>.Logic.Protocol.PlayerState state = 3;</code>
     * @return int
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     *状态
     *
     * Generated from protobuf field <code>.Logic.Protocol.PlayerState state = 3;</code>
     * @param int $var
     * @return $this
     */
    public function setState($var)
    {
        GPBUtil::checkEnum($var, \Logic\Protocol\PlayerState::class);
        $this->state = $var;

        return $this;
    }

}

