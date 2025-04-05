<?php

namespace App\Models;

use App\Enums\EmailStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 
 *
 * @property int $id
 * @property string $email 邮箱
 * @property string $email_uri 邮箱地址
 * @property EmailStatus $status 状态
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Email newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Email newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Email query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Email whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Email whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Email whereEmailUri($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Email whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Email whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Email whereUpdatedAt($value)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EmailLog> $logs
 * @property-read int|null $logs_count
 * @mixin \Eloquent
 */
class Email extends Model
{
    
    protected $fillable = ['email', 'email_uri', 'status'];

    protected $casts = [
        'status' => EmailStatus::class,
    ];

    /**
     * 关联日志
     */
    public function logs(): HasMany
    {
        return $this->hasMany(EmailLog::class, 'email_id');
    }

    /**
     * 创建日志
     */
    public function createLog(string $message, array $data = []): EmailLog
    {
        return $this->logs()->create([
            'email' => $this->email,
            'message' => $message,
            'data' => $data,
            'email_id'=>$this->id,
        ]);
    }
    
    /**
     * 检查邮箱是否可用
     */
    public function isAvailable(): bool
    {
        return $this->status === EmailStatus::AVAILABLE;
    }
}
