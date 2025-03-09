<?php

return [

    'name'      => 'proxyManager',

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
    */
    'providers' => [
        'huashengdaili' => [
            'default_mode' => 'dynamic',
            'connector' => \Weijiajia\HttpProxyManager\Proxys\HuaSheng\HuaShengConnector::class,
            'mode'      => [
                'dynamic' => [
                    'request'        => \Weijiajia\HttpProxyManager\Proxys\HuaSheng\Request\Dynamic::class,
                    'dto'            => \Weijiajia\HttpProxyManager\Proxys\HuaSheng\Data\Dynamic::class,
                    'default_config' => [
                        'time'      => 10,      // 提取的IP时长（分钟）
                        'count'     => 1,      // 提取的IP数量
                        'only'      => 0,       // 是否去重（1=去重，0=不去重）
                        'province'  => '',   // 省份编号
                        'city'      => '',      // 城市编号
                        'iptype'    => 'tunnel', // IP类型（tunnel=隧道，direct=直连）
                        'pw'        => 'no',      // 是否需要账号密码
                        'protocol'  => 'HTTP', // IP协议
                        'separator' => 1,   // 分隔符样式
                        'type'      => 'json',  // 返回类型
                        'format'    => 'city,time', // 其他返回信息
                        'session'   => '', // 会话密钥
                    ],
                ],
            ],
        ],

        'stormproxies' => [
            'default_mode' => 'dynamic',
            'connector' => \Weijiajia\HttpProxyManager\Proxys\Stormproxies\StormConnector::class,
            'mode'      => [
                'flow'    => [
                    'request'        => \Weijiajia\HttpProxyManager\Proxys\Stormproxies\Request\AccountPassword::class,
                    'dto'            => \Weijiajia\HttpProxyManager\Proxys\Stormproxies\Data\AccountPassword::class,
                    'default_config' => [
                        'session' => null,  
                        'life'    => 1,
                        'area'    => null,
                        'city'    => null,
                        'state'   => null,
                        'ip'      => null,
                    ],
                ],
                'dynamic' => [
                    'request'        => \Weijiajia\HttpProxyManager\Proxys\Stormproxies\Request\Dynamic::class,
                    'dto'            => \Weijiajia\HttpProxyManager\Proxys\Stormproxies\Data\Dynamic::class,
                    'default_config' => [
                        'app_key'       => 'xxxxx',
                        'app_secret'       => '',
                        'ep'       => 'hk',
                        'cc'       => 'cn',
                        'num'      => 1,
                        'city'     => null,
                        'state'    => null,
                        'life'     => 1,
                        'protocol' => 'http',
                        'format'   => 2,
                        'lb'       => 1,
                    ],
                ],
            ],
        ],

        'wandou' => [
            'default_mode' => 'dynamic',
            'connector' => \Weijiajia\HttpProxyManager\Proxys\Wandou\WandouConnector::class,
            'mode'      => [
                'flow'    => [
                    'request'        => \Weijiajia\HttpProxyManager\Proxys\Wandou\Request\AccountPassword::class,
                    'dto'            => \Weijiajia\HttpProxyManager\Proxys\Wandou\Data\AccountPassword::class,
                    'default_config' => [
                        'username' => null,
                        'password' => null,
                        'life' => 1,          // 尽可能保持一个ip的使用时间
                        'isp'      => null,          // 运营商
                        'pid'      => 0,       // 省份id
                        'cid'      => null,       // 城市id
                        'host'     => 'gw.wandouapp.com',           // 代理的地址
                        'port'     => '1000',           // 代理的地址
                    ],
                ],
                'dynamic' => [
                    'request'        => \Weijiajia\HttpProxyManager\Proxys\Wandou\Request\Dynamic::class,
                    'dto'            => \Weijiajia\HttpProxyManager\Proxys\Wandou\Data\Dynamic::class,
                    'default_config' => [
                        'app_key' => null,    // 必需,开放的app_key
                        'num'     => 1,           // 可选,单次提取IP数量
                        'xy'      => 1,            // 可选,代理协议 1.http 3.socks
                        'type'    => 2,          // 可选,返回数据格式 1.txt 2.json
                        'lb'      => 1,            // 可选,分割符
                        'nr'      => 0,            // 可选,自动去重
                        'area_id' => 0,       // 可选,地区id
                        'isp'     => 0,           // 可选,运营商
                    ],
                ],
            ],
        ],
        'iproyal' => [
            'default_mode' => 'dynamic',
            'connector' => \Weijiajia\HttpProxyManager\Proxys\IpRoyal\IpRoyalConnector::class,
            'mode'      => [
                'flow' => [
                    'dto'            => \Weijiajia\HttpProxyManager\Proxys\IpRoyal\Data\Residential::class,
                    'request'        => \Weijiajia\HttpProxyManager\Proxys\IpRoyal\Request\Residential::class,
                    'default_config' => [
                        'protocol'         => 'http',
                        'sticky_session'   => false,
                        'session_duration' => 10,
                        'streaming'        => false,
                        'skip_isp_static'  => false,
                        'skip_ips_list'    => null,
                        'endpoint'         => 'geo.iproyal.com',
                    ],
                ],
            ],
        ],
        'smartdaili' => [
            'default_mode' => 'flow',
            'connector' => \Weijiajia\HttpProxyManager\Proxys\Smartdaili\SmartdailiConnector::class,
            'mode'      => [
                'flow' => [
                    'request'        => \Weijiajia\HttpProxyManager\Proxys\Smartdaili\Request\AccountPassword::class,
                    'dto'            => \Weijiajia\HttpProxyManager\Proxys\Smartdaili\Data\AccountPassword::class,
                    'default_config' => [

                    ],
                ],
            ],
        ],
    ],
];
