<?php

namespace App\Enums;

use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Request\Account\Appleid;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Request\Account\Password;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Request\Account\Widget\Account as AccountWidget;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Request\Account\Account;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Request\Captcha;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Request\Account\Validate;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Request\Account\SendVerificationEmail;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Request\Account\VerificationEmail;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Request\Account\SendVerificationPhone;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleId\Request\Account\VerificationPhone;
use Weijiajia\HttpProxyManager\ProxyConnector;
use Weijiajia\DecryptVerificationCode\CloudCode\CloudCodeConnector;
use Weijiajia\IpAddress\Request as IpAddressRequest;

enum Request: string
{
    case ACCOUNT = Account::class;
    case ACCOUNT_WIDGET = AccountWidget::class;

    case APPLEID = Appleid::class;

    case PASSWORD = Password::class;
    case CAPTCHA = Captcha::class;
    case VALIDATE = Validate::class;
    case SEND_VERIFICATION_EMAIL = SendVerificationEmail::class;
    case VERIFICATION_EMAIL = VerificationEmail::class;
    case SEND_VERIFICATION_PHONE = SendVerificationPhone::class;
    case VERIFICATION_PHONE = VerificationPhone::class;
    case PROXYCONNECTOR = ProxyConnector::class;
    case CLOUDCODECONNECTOR = CloudCodeConnector::class;
    case IPADDRESSMANAGER = IpAddressRequest::class;

    public function label(): string
    {
        return match($this) {
            self::ACCOUNT_WIDGET => '初始化 session_id',
            self::ACCOUNT => '注册账号信息',
            self::APPLEID => '验证账号信息',
            self::PASSWORD => '验证密码信息',
            self::CAPTCHA => '获取验证码',
            self::VALIDATE => '验证账号和验证码',
            self::SEND_VERIFICATION_EMAIL => '发送验证邮件',
            self::VERIFICATION_EMAIL => '验证验证邮件',
            self::SEND_VERIFICATION_PHONE => '发送验证短信',
            self::VERIFICATION_PHONE => '验证验证短信',
            self::PROXYCONNECTOR => '获取代理',
            self::CLOUDCODECONNECTOR => '解码验证码',
            self::IPADDRESSMANAGER => '获取IP地址',
        };
    }

    /**
     * 通过类名查找匹配的枚举 case
     *
     * @param string $className 完整类名
     * @return self|null 找到的枚举 case 或 null
     */
    public static function fromClass(string $className): ?self
    {
        // 获取所有 case
        $cases = self::cases();

        // 检查完全匹配
        foreach ($cases as $case) {

            //判断 $className是否继承或者实现$case->value
            if ($case->value === $className || is_subclass_of($className, $case->value)) {
                return $case;
            }
        }

        return null;
    }

}
