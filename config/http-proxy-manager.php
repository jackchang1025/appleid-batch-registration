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
    */
    'drivers' => [
        'huashengdaili' => [
            'mode' => 'extract_ip',
            'extract_ip' => [
                'time'      => 10,      // 提取的IP时长（1=1分钟，3=3分钟，5=5分钟，10=10分钟，以此类推）
                'count'     => 1,      // 提取的IP数量
                'only'      => 0,       // 是否去重（1=去重，0=不去重）
                'province'  => '',   // 省份编号
                'city'      => '',      // 城市编号
                'iptype'    => 'tunnel', // IP类型（tunnel=隧道，direct=直连）
                'pw'        => 'no',      // 是否需要账号密码
                'protocol'  => 'HTTP', // IP协议（HTTP是HTTP/HTTPS，s5是socks5）
                'separator' => 1,   // 分隔符样式（1=回车换行(\r\n)，2=回车(\r)；3=换行(\n)；4=Tab(\t)；5=空格( )）
                'type'      => 'json',  //可选json和text）
                'format'    => 'city,time', // 其他返还信息（null是不需要返回城市和IP过期时间；city是返回城市省份；time是返回IP过期时间；city,time是代表返回城市和IP过期时间）
                'session'   => '', // 会话密钥
            ],
        ],

        'stormproxies' => [
            'mode' => 'extract_ip',
            'direct_connection_ip'    => [
                'session' => null,
                'life'    => 1,
                'area'    => null,
                'city'    => null,
                'state'   => null,
                'ip'      => null,
                'sticky_session' => false,
            ],
            'extract_ip' => [
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

        'wandou' => [
            'mode' => 'extract_ip',
            'direct_connection_ip'    => [
                    'username' => null,
                    'password' => null,
                    'life' => 1,          // 尽可能保持一个ip的使用时间
                    'isp'      => null,          // 运营商
                    'pid'      => 0,       // 省份id
                    'cid'      => null,       // 城市id
                    'host'     => 'gw.wandouapp.com',           // 代理的地址
                    'port'     => '1000',           // 代理的地址
                    'sticky_session' => false,
                ],
                'extract_ip' => [
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
        'iproyal' => [
            'mode' => 'direct_connection_ip',
            'direct_connection_ip' => [
                //国家配置的键。此参数的值是两个字母的国家代码
                'country'          => null,
                //是城市配置的键。此参数的值是城市名称
                'city'             => null,
                //用于定位美国的某个州。该值应为该州的名称
                'state'            => null,
                //isp 用于定位某个位置的特定ISP （Internet 服务提供商）。该值应为提供商的串联名称。
                'isp'              => null,
                //是区域配置的关键。添加此值将告诉我们的路由器过滤位于此区域的代理
                'region'           => null,
                //配置代理时，考虑最适合您需求的协议非常重要。有两种主要类型可供选择：HTTP/HTTPS 和 SOCKS5。每种协议在其不同的端口集上运行并服务于不同的目的。我们提供两种主要类型的代理协议。
                'protocol'         => 'http',
                //是否启用粘性会话
                'sticky_session'   => false,
                //指示路由器会话保持有效的持续时间
                'session_duration' => 10,
                //_session 键指示我们的路由系统为连接创建或解析唯一会话。分配给此键的值必须是随机字母数字字符串，长度恰好为8 个字符。这可确保会话的唯一性和完整性。
                'session' => null,
                //_lifetime-键指示路由器会话保持有效的持续时间。最短持续时间设置为1 秒，最长为7 天。这里的格式非常重要：只能指定一个时间单位。此参数在定义粘性会话的运行跨度、平衡会话稳定性和安全性需求方面起着关键作用。
                'lifetime' => '10m',
                //激活"高端代理池"选项后，您将能够访问我们精选的最快、最可靠的代理。但请注意，这种增强质量的代价是可用代理池比通常可访问的代理池要小。
                'streaming'        => false,
                //要启用此功能，您需要添加值为1 的_skipispstatic-键。
                'skipispstatic'  => null,
                //IP 跳过功能使您能够编译多个 IP 范围列表，这些列表将在代理连接的 IP 解析过程中自动绕过。要启用此功能，您需要添加_skipipslist-键，该键的值是生成列表的ULID （id）。
                'skipipslist'    => null,
                //强制随机
                'forcerandom'      => 1,
                //主机
                'host'             => 'geo.iproyal.com',
                //端口
                'port'             => 443,
            ],
        ],
        'smartdaili' => [
            'mode' => 'extract_ip',
            'extract_ip' => [],
        ],
        'smartproxy' => [
            'mode' => 'extract_ip',
            'extract_ip' => [
                //app_key: 开放的app_key,可以通过用户个人中心获取
                'app_key' => null,
                //套餐id,提取界面选择套餐可指定对应套餐进行提取
                'pt' => null,
                //单次提取IP数量,最大500
                'num' => 1,
                //全球地区
                'cc' => null,
                //州代码列表
                'state' => null,
                //城市代码列表
                'city' => null,
                //单次提取IP时间 最大7200分钟
                'life' => 1,
                //代理协议,1.http/socks5
                'protocol' => 'http',
                //返回数据格式,1.txt 2.json
                'format' => 1,
                //返回数据格式,1.换行回车 2.换行 3.回车 4.Tab
                'lb' => 1,
            ],
            'direct_connection_ip' => [
                //用户名
                'username' => null,
                //密码
                'password' => null,
                //会话
                'session' => null,
                //是否启用粘性会话
                'sticky_session' => false,
                //保持ip使用的时间,单位分钟，最小1,最大24*60
                'life' => 1,
                //全球地区code 例如：美国 area-US点击查看
                'area' => null,
                //所属城市 例如：纽约 city-newyork点击查看
                'city' => null,
                //州代码 例如：纽约 state-Newyork点击查看
                'state' => null,
                //指定数据中心地址
                'ip' => null,
            ],
        ],
        'proxyscrape' => [
            'mode' => 'extract_ip',
            'extract_ip' => [
                'request' => 'display_proxies',
                'country' => 'us',
                'protocol' => 'http',
                'proxy_format' => 'protocolipport',
                'format' => 'json',
                'timeout' => 20000,
            ],
        ],
        'oxylabs' => [
            'mode' => 'direct_connection_ip',
            'direct_connection_ip' => [
                'username' => null,
                'password' => null,
                'host' => null,
                'port' => null,
                    //国家代码，不区分大小写,2 个字母 3166-1 alpha-2 格式。例如，DE 代表德国，GB 代表英国，TH 代表泰国。您可以在此处找到更多关于如何使用特定国家/地区的代理的详情
                'cc'=>null,
                //城市名称，不区分大小写（英语）。这个参数必须伴随着 cc 参数以提升准确度，例如 cc-GB-city-london 表示英国伦敦；cc-DE-city-berlin 表示德国柏林。对于名称超过 2 个单词的城市，以 _ 代替空格，如 city-st_petersburg 或 city-rio_de_janeiro。我们支持世界上的任何城市，但我们不保证所有城市都有代理。大多数热门城市覆盖率良好，有诸多代理可供选择。您可以下载本表下面所支持城市文件以供参考。点击此处了解更多关于城市级目标的信息。
                'city' => null,
                //美国州名不区分大小写，以 us_ 开头，如 us_california、us_illinois。可在本表下面下载所支持州的完整列表。
                'st' => null,
                //会话 ID 在接下来的查询中保留相同的 IP。该会话在 10 分钟后到期。之后将为该会话 ID 分配一个新 IP 地址。随机字符串；支持 0-9、A-z 字符。
                'sessid' => null,
        //会话持续时间，以分钟为单位。默认值为 10 分钟。
                'sesstime' => 10,
                //是否启用粘性会话
                'sticky_session' => false,


                'protocol' => 'http',


            ],
        ],
    ],
];
