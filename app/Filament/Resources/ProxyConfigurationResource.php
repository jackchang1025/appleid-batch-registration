<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProxyConfigurationResource\Pages;
use App\Models\ProxyConfiguration;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Split;
use Filament\Forms\Form;
use Filament\Resources\Resource;

class ProxyConfigurationResource extends Resource
{
    protected static ?string $model = ProxyConfiguration::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $label = '代理设置';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Split::make([
                    Section::make('Main Content')
                        ->schema([
                            Forms\Components\Tabs::make('Driver Configuration')
                                ->tabs([
                                    Forms\Components\Tabs\Tab::make('Hailiangip')
                                        ->schema(self::getHailiangipSchema()),

                                    Forms\Components\Tabs\Tab::make('Stormproxies')
                                        ->schema(self::getStormproxiesSchema()),

                                    Forms\Components\Tabs\Tab::make('Huashengdaili')
                                        ->schema(self::getHuashengdaili()),

                                    Forms\Components\Tabs\Tab::make('Wandou')
                                        ->schema(self::getWandouSchema()),

                                    Forms\Components\Tabs\Tab::make('IPRoyal')
                                        ->schema(self::getIproyalSchema()),

                                    Forms\Components\Tabs\Tab::make('Smartdaili')
                                        ->schema(self::getSmartdailiSchema()),

                                    Forms\Components\Tabs\Tab::make('smartproxy')
                                    ->schema(self::getSmartProxySchema()),
                                ]),
                        ])
                        ->columnSpan(['lg' => 3]),
                    Section::make('Meta Information')
                        ->schema([

                            Forms\Components\Select::make('configuration.default_driver')
                                ->label('代理驱动')
                                ->options([
                                    'hailiangip' => 'Hailiangip',
                                    'stormproxies' => 'Stormproxies',
                                    'huashengdaili' => 'Huashengdaili',
                                    'wandou'        => '豌豆代理',
                                    'iproyal'       => 'IPRoyal',
                                    'smartdaili'    => 'Smartdaili',
                                    'smartproxy'    => 'SmartProxy',
                                ])
                                ->required()
                                ->default('stormproxies')
                                ->helperText('选择默认代理驱动')
                                ->reactive(),

                            Forms\Components\Toggle::make('status')
                                ->label('是否开启代理')
                                ->required()
                                ->default(false)
                                ->helperText('开启则使用代理，关闭则不使用代理'),

                            Forms\Components\Toggle::make('configuration.ipaddress_enabled')
                                ->label('根据用户 IP 地址自动选择代理')
                                ->required()
                                ->default(false)
                                ->helperText('开启后将根据用户 IP 地址选择代理 IP的地址，关闭则使用随机的代理 IP 地址,注意暂时只支持国内 IP 地址'),
                        ])
                        ->columnSpan(['lg' => 1]),
                ])
                    ->from('md')
                    ->columnSpanFull(),
            ]);
    }

    protected static function getHailiangipSchema(): array{
        return [
            Forms\Components\Select::make('configuration.hailiangip.mode')
                ->options([
                    'direct_connection_ip' => '默认账密模式',
                    'extract_ip' => '提取模式',
                ])
                ->default('direct_connection_ip')
                ->helperText('选择代理模式'),

            Forms\Components\TextInput::make('configuration.hailiangip.orderId')
//                ->required()
                ->helperText('代理订单ID'),
            Forms\Components\TextInput::make('configuration.hailiangip.pwd')
//                ->required()
                ->password()
                ->helperText('代理订单密码'),

            Forms\Components\TextInput::make('configuration.hailiangip.secret')
//                ->required()
                ->helperText('代理订单密钥'),

//            Forms\Components\TextInput::make('configuration.hailiangip.pid')
//                ->default('-1')
//                ->helperText('省份ID，-1表示随机'),
//            Forms\Components\TextInput::make('configuration.hailiangip.cid')
//
//                ->default('-1')
//                ->helperText('城市ID，-1表示随机'),
//            Forms\Components\Toggle::make('configuration.hailiangip.sip')
//                ->default(1)
//                ->helperText('是否切换IP：关闭表示自动切换，开启表示不能切换'),

//            Forms\Components\TextInput::make('configuration.hailiangip.uid')
//                ->default('')
//                ->helperText('自定义UID，相同的UID会尽可能采用相同的IP'),

//            Forms\Components\Select::make('configuration.hailiangip.type')
//                ->options([
//                    1 => 'HTTP/HTTPS',
//                ])
//                ->default(1)
//                ->helperText('选择IP协议'),
//            Forms\Components\TextInput::make('configuration.hailiangip.num')
//                ->numeric()
//                ->default(1)
//                ->minLength(1, 200)
//                ->maxLength(200)
////                ->helperText('提取数量：1-200之间'),
            Forms\Components\TextInput::make('configuration.hailiangip.pid')
                ->default(-1)
                ->helperText('省份ID：-1表示中国'),
//            Forms\Components\TextInput::make('configuration.hailiangip.unbindTime')
//                ->numeric()
//                ->default(600)
//                ->minValue(1)
//                ->helperText('占用时长（单位：秒）'),
            Forms\Components\TextInput::make('configuration.hailiangip.cid')
                ->default('')
                ->helperText('城市ID，留空表示随机'),
            Forms\Components\Toggle::make('configuration.hailiangip.noDuplicate')
                ->default(0)
                ->helperText('是否去重：关闭表示不去重，开启表示24小时去重'),
//            Forms\Components\Select::make('configuration.hailiangip.dataType')
//                ->options([
//                    0 => 'JSON',
//                ])
//                ->default(0)
//                ->helperText('选择返回的数据格式'),
//            Forms\Components\Toggle::make('configuration.hailiangip.singleIp')
//                ->default(0)
//                ->helperText('异常切换：关闭表示切换，开启表示不切换'),
        ];
    }

    protected static function getStormproxiesSchema(): array{
        return [
            Forms\Components\Select::make('configuration.stormproxies.mode')
                ->options([
                    'direct_connection_ip' => '账密模式',
                    'extract_ip' => '提取模式',
                ])
//                ->required()
                ->default('direct_connection_ip')
                ->helperText('选择代理模式'),

            Forms\Components\Select::make('configuration.stormproxies.host')
                ->options([
                    'proxy.stormip.cn' => '智能',
                    'hk.stormip.cn' => '亚洲区域',
                    'us.stormip.cn' => '美洲区域',
                    'eu.stormip.cn' => '欧洲区域',
                ])
//                ->required()
                ->default('proxy.stormip.cn')
                ->helperText('选择代理网络(代理网络是指中转服务器的位置)'),

            Forms\Components\Select::make('configuration.stormproxies.area')
                ->options([
                    '' => '全球混播',
                    'hk' => '香港',
                    'us' => '美国',
                    'cn' => '中国',
                ])
                ->default('cn')
                ->helperText('选择节点国家'),

            Forms\Components\TextInput::make('configuration.stormproxies.username')
//                ->required()
                ->helperText('用户名'),

            Forms\Components\TextInput::make('configuration.stormproxies.password')
//                ->required()
                ->helperText('密码'),

            Forms\Components\TextInput::make('configuration.stormproxies.app_key')
//                ->required()
                ->helperText('开放的app_key,可以通过用户个人中心获取'),

            Forms\Components\TextInput::make('configuration.stormproxies.pt')
                ->helperText('套餐id,提取界面选择套餐可指定对应套餐进行提取'),
        ];
    }

    public static function getHuashengdaili():array
    {
        return [

            Forms\Components\Select::make('configuration.huashengdaili.mode')
                ->options([
                    'api' => 'api 提取',
                ])
                ->default('api')
                ->helperText('选择代理模式'),

            Forms\Components\TextInput::make('configuration.huashengdaili.session')
                ->helperText('session密钥'),

            Forms\Components\Toggle::make('configuration.huashengdaili.only')
                ->default(false)
                ->helperText('是否去重'),

            //                    Forms\Components\TextInput::make('configuration.huashengdaili.province')
            //                        ->numeric()
            //                        ->helperText('省份编号'),
            //
            //                    Forms\Components\TextInput::make('configuration.huashengdaili.city')
            //                        ->numeric()
            //                        ->helperText('城市编号'),

            Forms\Components\Select::make('configuration.huashengdaili.iptype')
                ->options([
                    'tunnel' => '隧道',
                    'direct' => '直连',
                ])
                ->default('direct')
                ->helperText('IP类型'),
            //                    Forms\Components\Select::make('configuration.huashengdaili.pw')
            //                        ->options([
            //                            'yes' => '是',
            //                            'no' => '否',
            //                        ])
            //                        ->required()
            //                        ->default('no')
            //                        ->helperText('是否需要账号密码'),

            //                    Forms\Components\Select::make('configuration.huashengdaili.protocol')
            //                        ->options([
            //                            'http' => 'HTTP/HTTPS',
            //                            's5' => 'SOCKS5',
            //                        ])
            //                        ->required()
            //                        ->default('http')
            //                        ->helperText('IP协议'),
            //
            //                    Forms\Components\Select::make('configuration.huashengdaili.separator')
            //                        ->options([
            //                            1 => '回车换行(\r\n)',
            //                            2 => '回车(\r)',
            //                            3 => '换行(\n)',
            //                            4 => 'Tab(\t)',
            //                            5 => '空格( )',
            //                        ])
            //                        ->required()
            //                        ->default(1)
            //                        ->helperText('分隔符样式'),
            //                    Forms\Components\Select::make('configuration.huashengdaili.format')
            //                        ->options([
            //                            'null' => '不需要返回城市和IP过期时间',
            //                            'city' => '返回城市省份',
            //                            'time' => '返回IP过期时间',
            //                            'city,time' => '返回城市和IP过期时间',
            //                        ])
            //                        ->required()
            //                        ->default('city,time')
            //                        ->helperText('其他返还信息'),

        ];
    }

    // 新增豌豆代理配置schema
    protected static function getWandouSchema(): array
    {
        return [
            Forms\Components\Select::make('configuration.wandou.mode')
                ->options([
                    'direct_connection_ip'    => '账密模式',
                    'extract_ip' => '提取模式',
                ])
                ->default('direct_connection_ip')
                ->helperText('选择代理模式'),

            Forms\Components\TextInput::make('configuration.wandou.app_key')
                ->helperText('提取模式时需要 开放的app_key,可以通过用户个人中心获取'),

            //            Forms\Components\TextInput::make('configuration.wandou.session')
            //                ->helperText('账密模式时需要 session 值'),

            Forms\Components\TextInput::make('configuration.wandou.username')
                ->helperText('账密模式时需要，可以通过用户个人中心获取'),

            Forms\Components\TextInput::make('configuration.wandou.password')
                ->helperText('账密模式时需要，可以通过用户个人中心获取'),

            Forms\Components\TextInput::make('configuration.wandou.host')
                ->default('api.wandoujia.com')
                ->helperText('账密模式时需要，可以通过用户个人中心获取'),

            Forms\Components\TextInput::make('configuration.wandou.port')
                ->default('1000')
                ->helperText(' 账密模式时需要，可以通过用户个人中心获取'),

            Forms\Components\Select::make('configuration.wandou.xy')
                ->options([
                    1 => 'HTTP/HTTPS',
                    3 => 'SOCKS5',
                ])
                ->default(1)
                ->helperText('代理协议'),

            Forms\Components\Select::make('configuration.wandou.isp')
                ->options([
                    null => '不限',
                    1    => '电信',
                    2    => '移动',
                    3    => '联通',
                ])
                ->default(null)
                ->helperText('运营商选择'),

            //            Forms\Components\TextInput::make('configuration.wandou.area_id')
            //                ->default(0)
            //                ->helperText('地区id,默认0全国混播,多个地区使用|分割'),

            Forms\Components\TextInput::make('configuration.wandou.num')
                ->numeric()
                ->default(1)
                ->helperText('提取模式时需要单次提取IP数量,最大100'),

            Forms\Components\Toggle::make('configuration.wandou.nr')
                ->default(false)
                ->helperText('提取模式时需要 是否自动去重'),

            Forms\Components\TextInput::make('configuration.wandou.life')
                ->numeric()
                ->default(1)
                ->helperText('提取模式时需要 尽可能保持一个ip的使用时间(分钟)'),

            //            Forms\Components\TextInput::make('configuration.wandou.pid')
            //                ->helperText('省份id'),
            //
            //            Forms\Components\TextInput::make('configuration.wandou.cid')
            //                ->helperText('城市id'),
        ];
    }

    protected static function getIproyalSchema(): array
    {
        return [

            Forms\Components\Select::make('configuration.iproyal.mode')
                ->options([
                    'direct_connection_ip'    => '账密模式',
                ])
                ->default('direct_connection_ip')
                ->helperText('选择代理模式'),

                Forms\Components\TextInput::make('configuration.iproyal.username')
                        ->label('用户名')
                        ->helperText('住宅代理用户名')
                        ->dehydrated(true), // 确保数据被保存

                    Forms\Components\TextInput::make('configuration.iproyal.password')
                        ->label('密码')
                        ->helperText('住宅代理密码')
                        ->dehydrated(true),

                    Forms\Components\TextInput::make('configuration.iproyal.host')
                        ->label('代理服务器')
                        ->default('geo.iproyal.com')
                        ->helperText('住宅代理服务器地址'),

                    Forms\Components\TextInput::make('configuration.iproyal.port')
                        ->label('端口')
                        ->default('12321')
                        ->helperText('住宅代理端口'),

                    Forms\Components\Select::make('configuration.iproyal.protocol')
                        ->options([
                            'http'   => 'HTTP/HTTPS',
                            'socks5' => 'SOCKS5',
                        ])
                        ->default('http')
                        ->helperText('选择代理协议'),

                    Forms\Components\TextInput::make('configuration.iproyal.country')
                        ->helperText('国家代码,如:us,cn等,留空表示随机')
                        ->default(''),

                    Forms\Components\TextInput::make('configuration.iproyal.state')
                        ->helperText('州/省代码,留空表示随机')
                        ->default(''),

                    Forms\Components\TextInput::make('configuration.iproyal.region')
                        ->helperText('区域代码,留空表示随机')
                        ->default(''),

                    Forms\Components\Toggle::make('configuration.iproyal.sticky_session')
                        ->label('启用粘性会话')
                        ->helperText('开启后将尽可能使用相同的IP')
                        ->default(false),

                    Forms\Components\TextInput::make('configuration.iproyal.lifetime')
                        ->helperText('会话持续时间(m:分钟,h:小时,d:天),仅在开启粘性会话时有效')
                        ->default('10m'),

                    Forms\Components\Toggle::make('configuration.iproyal.streaming')
                        ->label('启用高端池')
                        ->helperText('启用高端IP池')
                        ->default(false),

                    Forms\Components\Toggle::make('configuration.iproyal.skipispstatic')
                        ->label('跳过静态ISP')
                        ->helperText('启用跳过静态ISP功能')
                        ->default(false),
                    
                    Forms\Components\Toggle::make('configuration.iproyal.forcerandom')
                    ->label('强制随机')
                    ->helperText('强制随机IP')
                    ->default(true),

                    Forms\Components\TextInput::make('configuration.iproyal.skipipslist')
                        ->helperText('跳过IP列表ID')
                        ->default(null),
        ];
    }

    protected static function getSmartdailiSchema(): array
    {
        return [

            Forms\Components\Select::make('configuration.smartdaili.mode')
                ->options([
                    'direct_connection_ip' => '账密模式',
                ])
                ->default('direct_connection_ip')
                ->helperText('选择代理模式'),

            Forms\Components\TextInput::make('configuration.smartdaili.username')
                ->label('用户名')
                ->helperText('Smartdaili代理用户名'),

            Forms\Components\TextInput::make('configuration.smartdaili.password')
                ->label('密码')
                ->password()
                ->helperText('Smartdaili代理密码'),

            Forms\Components\TextInput::make('configuration.smartdaili.host')
                ->label('代理服务器')
                ->helperText('Smartdaili代理服务器地址'),

            Forms\Components\TextInput::make('configuration.smartdaili.port')
                ->label('端口')
                ->numeric()
                ->helperText('Smartdaili代理端口'),

            Forms\Components\Select::make('configuration.smartdaili.protocol')
                ->label('代理协议')
                ->options([
                    'http'   => 'HTTP/HTTPS',
                    'socks5' => 'SOCKS5',
                ])
                ->default('http')
                ->helperText('选择代理协议类型'),
        ];
    }

    protected static function getSmartProxySchema(): array
    {
        return [

            Forms\Components\Select::make('configuration.smartproxy.mode')
                ->options([
                    'direct_connection_ip' => '账密模式',
                    // 'extract_ip' => '提取模式',
                ])
                ->default('direct_connection_ip')
                ->helperText('选择代理模式'),

            Forms\Components\TextInput::make('configuration.smartproxy.username')
                ->label('套餐账号')
                ->helperText('套餐账号'),

            Forms\Components\TextInput::make('configuration.smartproxy.password')
                ->label('密码')
                ->helperText('SmartProxy代理密码'),

            Forms\Components\TextInput::make('configuration.smartproxy.host')
                ->label('代理服务器')
                ->helperText('SmartProxy代理服务器地址'),

            Forms\Components\TextInput::make('configuration.smartproxy.port')
                ->label('端口')
                ->numeric()
                ->helperText('SmartProxy代理端口'),

            Forms\Components\Select::make('configuration.smartproxy.protocol')
                ->label('代理协议')
                ->options([
                    'http'   => 'HTTP/HTTPS',
                    'socks5' => 'SOCKS5',
                ])
                ->default('http')
                ->helperText('选择代理协议类型'),

            Forms\Components\TextInput::make('configuration.smartproxy.area')
            ->helperText('国家代码,如:us,cn等,留空表示随机')
            ->default(''),

            Forms\Components\TextInput::make('configuration.smartproxy.city')
                ->helperText('州/省代码,留空表示随机')
                ->default(''),

            Forms\Components\TextInput::make('configuration.smartproxy.state')
                ->helperText('区域代码,留空表示随机')
                ->default(''),

            Forms\Components\Toggle::make('configuration.smartproxy.sticky_session')
                ->label('启用粘性会话')
                ->helperText('开启后将尽可能使用相同的IP')
                ->default(false),

            Forms\Components\TextInput::make('configuration.smartproxy.life')
                ->helperText('尽可能保持一个ip的使用时间(分钟),仅在开启粘性会话时有效')
                ->numeric()
                ->default(10)
                ->visible(fn(Forms\Get $get) => $get('configuration.smartproxy.sticky_session')),

            Forms\Components\TextInput::make('configuration.smartproxy.ip')
            ->helperText('指定数据中心地址'),

        ];
    }




    public static function getPages(): array
    {
        return [
            'index' => Pages\EditProxyConfiguration::route('/'),
        ];
    }
}
