<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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