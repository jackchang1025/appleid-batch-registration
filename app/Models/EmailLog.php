<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 
 *
 * @property int $id
 * @property int $email_id
 * @property string $email
 * @property string|null $status
 * @property string|null $message
 * @property array<array-key, mixed>|null $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Email $emailModel
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereEmailId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class EmailLog extends Model
{
    /**
     * 可批量赋值的属性
     */
    protected $fillable = [
        'email_id',
        'email',
        'status',
        'message',
        'data',
    ];
    
    /**
     * 属性类型转换
     */
    protected $casts = [
        'data' => 'array',
    ];
    
    /**
     * 关联邮箱
     */
    public function emailModel(): BelongsTo
    {
        return $this->belongsTo(Email::class, 'email_id');
    }
    
    /**
     * 记录日志
     */
    public function log(string $message, array $data = []): self
    {
        $this->message = $message;
        
        if (!empty($data)) {
            $this->data = array_merge($this->data ?? [], $data);
        }
        
        $this->save();
        
        return $this;
    }
} 