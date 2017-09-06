##业务逻辑说明
## Client数据包说明 ##

    struct ClientHeader
    {
        unsigned int    packLen,        (4字节) //包长度，包括数据字段
        unsigned short  version,        (2字节) //协议版本号
        unsigned int    appId,          (4字节) //应用ID 
        unsigned short  gatewayCmd,     (2字节) //网关命令
        unsigned int    protocolCmd,    (4字节) //协议命令
        unsigned short  checkSum,       (2字节) //数据校验
        unsigned int    msgIdx          (4字节) //数据包顺序
    }
    Client数据包头大小共22个字节
    
    客户端发包计算说明
             |-----------------------------------------------------------------|
    Packet = |      ClientHeader             |            ClientBody           |
             |-----------------------------------------------------------------|
             |      Header(22bytes)          |          Body:(Binary-stream)   |
             |-----------------------------------------------------------------|
             length = 2 + 4 + 2 + 4 + 2 + 4 + len(body)
## GatewayProtocol数据包说明 ##
GatewayProtocol协议是Gateway服务器和后端提供服务的服务器之间通信使用！

    HEAD_LEN 30
    struct GatewayProtocol
    {
        unsigned int          packLen,      (4字节)     //包长度，包括数据字段
        unsigned int          cmd,          (4字节)     //协议ID
        unsigned int          localIp,      (4字节)     //网关IP
        unsigned short        localPort,    (2字节)     //暴露给内网的网关端口
        unsigned int          clientIp,     (4字节)     //客户端IP，使用ip2long将ip转换成数字
        unsigned short        clientPort,   (2字节)     //客户端端口
        unsigned int          connectionId, (4字节)     //网关维持的连接ID
        unsigned short        gatewayPort,  (2字节)     //暴露给外网的网关端口
        unsigned int          extLen,       (4字节)     //扩展数据长度
        char[extLen]          extData,                  //扩展数据
        char[packLen - HEAD_LEN] body                   //数据包
    }
    GatewayProtocol数据包头大小共30个字节
    GatewayProtocol发包计算方式同客户端一样

    
    
    
