<?php

namespace App\Models;

use App\Enums\UserAgentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string|null $name
 * @property string $user_agent_string
 * @property string|null $browser
 * @property string|null $browser_version
 * @property string|null $platform
 * @property string|null $device_type
 * @property UserAgentStatus $status
 * @property int $usage_count
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $user_agent
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAgent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAgent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAgent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAgent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAgent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAgent whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAgent whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAgent whereUserAgent($value)
 * @mixin \Eloquent
 */
class UserAgent extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_agent',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => UserAgentStatus::class,
    ];

    /**
     * 随机获取一个活跃的User Agent
     *
     * @return self|null
     */
    public static function getRandomActive(): ?self
    {
        return self::where('status', UserAgentStatus::ACTIVE)
            ->inRandomOrder()
            ->first();
    }
} 