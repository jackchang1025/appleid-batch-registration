<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
