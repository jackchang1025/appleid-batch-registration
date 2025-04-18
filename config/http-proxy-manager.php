<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 默认代理驱动
    |--------------------------------------------------------------------------
    */
    'default'   => env('PROXY_DRIVER', 'huashengdaili'),

    /*
    |--------------------------------------------------------------------------
    | 代理服务商配置
    |--------------------------------------------------------------------------
    | 
    | 新的扁平化配置结构：
    | - 'mode': 指示使用哪种代理模式，可选值 'extract_ip' 或 'direct_connection_ip'
    | - 所有代理参数都直接放在根级别，不再使用嵌套结构
    | - 参数根据所选模式使用，未使用的参数会被忽略
    |
    */
    'drivers' => [
        // 华声代理
        'huashengdaili' => [
            // 模式选择：'extract_ip'=提取IP模式, 'direct_connection_ip'=直连模式
            'mode' => 'extract_ip',
            
            // 通用参数
            'protocol'  => 'HTTP',     // IP协议（HTTP是HTTP/HTTPS，s5是socks5）
            'username'  => null,       // 账号（直连模式必需）
            'password'  => null,       // 密码（直连模式必需）
            'host'      => null,       // 主机地址（直连模式必需）
            'port'      => null,       // 端口（直连模式必需）
            'session'   => '',         // 会话密钥
            
            // 提取IP模式参数
            'time'      => 10,         // 提取的IP时长（1=1分钟，3=3分钟，5=5分钟，10=10分钟，以此类推）
            'count'     => 1,          // 提取的IP数量
            'only'      => 0,          // 是否去重（1=去重，0=不去重）
            'province'  => '',         // 省份编号
            'city'      => '',         // 城市编号
            'iptype'    => 'tunnel',   // IP类型（tunnel=隧道，direct=直连）
            'pw'        => 'no',       // 是否需要账号密码
            'separator' => 1,          // 分隔符样式（1=回车换行(\r\n)，2=回车(\r)；3=换行(\n)；4=Tab(\t)；5=空格( )）
            'type'      => 'json',     // 可选json和text）
            'format'    => 'city,time',// 其他返还信息（null是不需要返回城市和IP过期时间；city是返回城市省份；time是返回IP过期时间；city,time是代表返回城市和IP过期时间）
            
            // 直连模式参数（虽然当前模式为extract_ip，但如需切换，可以配置这些参数）
            'lifetime'        => null,  // IP生存时间
            'sticky_session'  => false, // 是否启用粘性会话
        ],

        // Stormproxies
        'stormproxies' => [
            'mode' => 'extract_ip',
            
            // 通用参数
            'protocol'       => 'http',    // 代理协议
            'username'       => null,      // 用户名
            'password'       => null,      // 密码
            'host'           => 'proxy.stormip.cn', // 主机地址
            'port'           => 1000,      // 端口
            'session'        => null,      // 会话ID
            'life'           => 1,         // 生命周期/分钟
            'area'           => null,      // 地区
            'city'           => null,      // 城市
            'state'          => null,      // 州/省
            'ip'             => null,      // IP地址
            'sticky_session' => false,     // 粘性会话
            
            // 提取IP模式特定参数
            'app_key'       => 'xxxxx',   // 应用密钥
            'app_secret'    => '',        // 应用密钥
            'ep'            => 'hk',      // 端点
            'cc'            => 'cn',      // 国家代码
            'num'           => 1,         // 数量
            'format'        => 2,         // 格式
            'lb'            => 1,         // 负载均衡
        ],

        // 豌豆代理
        'wandou' => [
            'mode' => 'extract_ip',
            
            // 通用参数
            'username'       => null,      // 用户名
            'password'       => null,      // 密码
            'life'           => 1,         // 生命周期
            'isp'            => null,      // 运营商
            'pid'            => 0,         // 省份ID
            'cid'            => null,      // 城市ID
            'host'           => 'gw.wandouapp.com', // 主机
            'port'           => '1000',    // 端口
            'sticky_session' => false,     // 粘性会话
            
            // 提取IP模式特定参数
            'app_key'        => null,      // 应用密钥
            'num'            => 1,         // 数量
            'xy'             => 1,         // 协议 (1.http 3.socks)
            'type'           => 2,         // 返回格式 (1.txt 2.json)
            'lb'             => 1,         // 分隔符
            'nr'             => 0,         // 自动去重
            'area_id'        => 0,         // 地区ID
        ],
        
        // IPRoyal
        'iproyal' => [
            'mode' => 'direct_connection_ip',
            
            // 基本配置
            'country'          => null,     // 国家代码，两字母ISO代码
            'city'             => null,     // 城市名称
            'state'            => null,     // 美国州名
            'isp'              => null,     // 网络服务提供商
            'region'           => null,     // 区域
            'protocol'         => 'http',   // 协议类型：http或socks5
            'sticky_session'   => false,    // 是否启用粘性会话
            'session'          => null,     // 会话ID，8个字符的随机字母数字
            'lifetime'         => '10m',    // 会话有效期，最短1秒，最长7天
            'streaming'        => false,    // 是否启用高端代理池
            'skipispstatic'    => null,     // 略过ISP静态
            'skipipslist'      => null,     // 略过IP列表
            'forcerandom'      => 1,        // 强制随机
            'host'             => 'geo.iproyal.com', // 主机
            'port'             => null,     // 端口
            'username'         => null,     // 用户名
            'password'         => null,     // 密码
        ],
        
        // Smartdaili
        'smartdaili' => [
            'mode' => 'direct_connection_ip',
            
            // 直连配置
            'username'        => null,     // 用户名
            'password'        => null,     // 密码
            'session'         => null,     // 会话ID
            'sticky_session'  => false,    // 粘性会话
            'life'            => 10,       // 生命周期/分钟
            'country'         => null,     // 国家
            'city'            => null,     // 城市
            'state'           => null,     // 州/省
            'ip'              => null,     // IP地址
            'protocol'        => 'http',   // 协议
            'host'            => 'gate.visitxiangtan.com', // 主机
            'port'            => 7000,     // 端口
        ],
        
        // SmartProxy
        'smartproxy' => [
            'mode' => 'extract_ip',
            
            // 通用配置
            'protocol'        => 'http',   // 协议
            'host'            => null,     // 主机
            'port'            => 1000,     // 端口
            'username'        => null,     // 用户名
            'password'        => null,     // 密码
            'session'         => null,     // 会话ID
            'sticky_session'  => false,    // 粘性会话
            'life'            => 1,        // 生命周期/分钟
            'area'            => null,     // 地区代码
            'city'            => null,     // 城市
            'state'           => null,     // 州/省
            'ip'              => null,     // 指定IP
            
            // 提取IP模式特定参数
            'app_key'         => null,     // 开放的app_key
            'pt'              => null,     // 套餐ID
            'num'             => 1,        // 提取数量，最大500
            'cc'              => null,     // 全球地区代码
            'format'          => 1,        // 返回格式：1.txt 2.json
            'lb'              => 1,        // 返回分隔符：1.换行回车 2.换行 3.回车 4.Tab
        ],
        
        // ProxyScrape
        'proxyscrape' => [
            'mode' => 'extract_ip',
            
            // 提取配置
            'request'         => 'display_proxies', // 请求类型
            'country'         => 'us',      // 国家代码
            'protocol'        => 'http',    // 协议
            'proxy_format'    => 'protocolipport', // 代理格式
            'format'          => 'json',    // 返回格式
            'timeout'         => 20000,     // 超时时间（毫秒）
        ],
        
        // Oxylabs
        'oxylabs' => [
            'mode' => 'direct_connection_ip',
            
            // 直连配置
            'username'        => null,     // 用户名
            'password'        => null,     // 密码
            'host'            => null,     // 主机
            'port'            => null,     // 端口
            'cc'              => null,     // 国家代码，不区分大小写,2个字母3166-1 alpha-2格式
            'city'            => null,     // 城市名称，不区分大小写（英语）
            'st'              => null,     // 美国州名，不区分大小写，以us_开头
            'sessid'          => null,     // 会话ID，保留相同IP的随机字符串
            'sesstime'        => 10,       // 会话持续时间，默认10分钟
            'sticky_session'  => false,    // 是否启用粘性会话
            'protocol'        => 'http',   // 协议
        ],
    ],
];
