<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 
 *
 * @property int $id
 * @property string|null $ip_uri 代理服务提供者提供的IP
 * @property string|null $real_ip 连接代理后的真实IP
 * @property string|null $proxy_provider 代理服务提供者
 * @property string|null $country_code 真实IP国家代码
 * @property string|null $exception_message 异常信息
 * @property bool $is_success 本次注册是否成功
 * @property array<array-key, mixed>|null $ip_info IP信息
 * @property int|null $email_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Email|null $email
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProxyIpStatistic newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProxyIpStatistic newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProxyIpStatistic query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProxyIpStatistic whereCountryCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProxyIpStatistic whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProxyIpStatistic whereEmailId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProxyIpStatistic whereExceptionMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProxyIpStatistic whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProxyIpStatistic whereIpInfo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProxyIpStatistic whereIpUri($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProxyIpStatistic whereIsSuccess($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProxyIpStatistic whereProxyProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProxyIpStatistic whereRealIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProxyIpStatistic whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ProxyIpStatistic extends Model
{
    use HasFactory;

    protected $fillable = [
        'ip_uri',
        'real_ip',
        'proxy_provider',
        'country_code',
        'is_success',
        'email_id',
        'ip_info',
        'exception_message',
    ];

    protected $casts = [
        'is_success' => 'boolean',
        'ip_info' => 'array',
    ];

    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }
}
