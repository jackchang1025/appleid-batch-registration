<?php

namespace App\Enums;

enum EmailStatus: string
{
    case AVAILABLE = 'available';
    case PROCESSING = 'processing';
    case REGISTERED = 'registered';
    case FAILED = 'failed';
    case INVALID = 'invalid';
    
    /**
     * 获取状态的中文描述
     */
    public function label(): string
    {
        return match($this) {
            self::AVAILABLE => '可用',
            self::PROCESSING => '处理中',
            self::REGISTERED => '已注册',
            self::FAILED => '注册失败',
            self::INVALID => '无效',
        };
    }
    
    /**
     * 获取状态的显示颜色
     */
    public function color(): string
    {
        return match($this) {
            self::AVAILABLE => 'success',
            self::PROCESSING => 'warning',
            self::REGISTERED => 'primary',
            self::FAILED => 'danger',
            self::INVALID => 'gray',
        };
    }
    
    /**
     * 获取所有状态的标签映射
     */
    public static function labels(): array
    {
        return [
            self::AVAILABLE->value => self::AVAILABLE->label(),
            self::PROCESSING->value => self::PROCESSING->label(),
            self::REGISTERED->value => self::REGISTERED->label(),
            self::FAILED->value => self::FAILED->label(),
            self::INVALID->value => self::INVALID->label(),
        ];
    }
    
    /**
     * 获取所有状态的颜色映射
     */
    public static function colors(): array
    {
        return [
            self::AVAILABLE->value => self::AVAILABLE->color(),
            self::PROCESSING->value => self::PROCESSING->color(),
            self::REGISTERED->value => self::REGISTERED->color(),
            self::FAILED->value => self::FAILED->color(),
            self::INVALID->value => self::INVALID->color(),
        ];
    }
} 