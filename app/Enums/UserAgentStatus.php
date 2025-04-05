<?php

namespace App\Enums;

enum UserAgentStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    
    /**
     * 获取状态的中文描述
     */
    public function label(): string
    {
        return match($this) {
            self::ACTIVE => '启用',
            self::INACTIVE => '禁用',
        };
    }
    
    /**
     * 获取状态的显示颜色
     */
    public function color(): string
    {
        return match($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'danger',
        };
    }
    
    /**
     * 获取所有状态的标签映射
     */
    public static function labels(): array
    {
        return [
            self::ACTIVE->value => self::ACTIVE->label(),
            self::INACTIVE->value => self::INACTIVE->label(),
        ];
    }
    
    /**
     * 获取所有状态的颜色映射
     */
    public static function colors(): array
    {
        return [
            self::ACTIVE->value => self::ACTIVE->color(),
            self::INACTIVE->value => self::INACTIVE->color(),
        ];
    }
} 