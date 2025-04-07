<?php

namespace App\Enums;

enum PhoneStatus: string
{
    case NORMAL = 'normal';
    case INVALID = 'invalid';
    case BOUND = 'bound';
    case BINDING = 'Binding';
    
    /**
     * 获取状态的中文描述
     */
    public function label(): string
    {
        return match($this) {
            self::NORMAL => '正常',
            self::INVALID => '失效',
            self::BOUND => '已绑定',
            self::BINDING => '绑定中',
        };
    }
    
    /**
     * 获取状态的显示颜色
     */
    public function color(): string
    {
        return match($this) {
            self::NORMAL => 'gray',
            self::INVALID => 'warning',
            self::BOUND => 'success',
            self::BINDING => 'danger',
        };
    }
    
    /**
     * 获取所有状态的标签映射
     */
    public static function labels(): array
    {
        return [
            self::NORMAL->value => self::NORMAL->label(),
            self::INVALID->value => self::INVALID->label(),
            self::BOUND->value => self::BOUND->label(),
            self::BINDING->value => self::BINDING->label(),
        ];
    }
    
    /**
     * 获取所有状态的颜色映射
     */
    public static function colors(): array
    {
        return [
            self::NORMAL->value => self::NORMAL->color(),
            self::INVALID->value => self::INVALID->color(),
            self::BOUND->value => self::BOUND->color(),
            self::BINDING->value => self::BINDING->color(),
        ];
    }
}