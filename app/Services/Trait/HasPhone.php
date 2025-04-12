<?php

namespace App\Services\Trait;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Redis;
use App\Models\Phone;
use Illuminate\Support\Facades\DB;
use App\Services\CountryLanguageService;
use App\Enums\PhoneStatus;
use Illuminate\Support\Collection;
trait HasPhone
{
     // 添加类常量
     public const PHONE_BLACKLIST_KEY = 'phone_code_blacklist';
     public const BLACKLIST_EXPIRE_SECONDS = 3600; // 1小时过期

    protected ?Phone $phone = null;

    protected array $usedPhones = [];

    protected ?CountryLanguageService $country = null;

    /**
     * @throws ModelNotFoundException
     * @return Phone
     */
    public function getPhone(): Phone
    {
        return DB::transaction(function () {
            // 获取有效黑名单ID
            $blacklistIds = self::getActiveBlacklistIds();

            // 创建基本查询
            $query = Phone::query()
                ->where('status', PhoneStatus::NORMAL)
                ->whereNotNull(['phone_address', 'phone'])
                ->whereNotIn('id', $this->usedPhones)
                ->whereNotIn('id', $blacklistIds);
            
            // 如果国家条件存在，直接添加国家筛选条件
            if ($this->country) {
                $query->where(function($subQuery) {
                    $subQuery->where('country_code_alpha3', $this->country->getAlpha3Code())
                            ->orWhere('country_code', $this->country->getAlpha2Code());
                });
            }
            
            // 完成查询
            $phone = $query->orderBy('id','desc')
                ->lockForUpdate()
                ->firstOrFail();

            $phone->update(['status' => PhoneStatus::BINDING]);

            $this->usedPhones[] = $phone->id;

            return $phone;
        });
    }

    /**
     * 获取当前有效的黑名单手机号ID
     *
     * @return array
     */
    protected static function getActiveBlacklistIds(): array
    {
        // 获取所有黑名单记录
        $blacklist = Redis::hgetall(self::PHONE_BLACKLIST_KEY);

        // 过滤出未过期的黑名单手机号ID
        return array_keys(array_filter($blacklist, function ($timestamp) {
            return (now()->timestamp - $timestamp) < self::BLACKLIST_EXPIRE_SECONDS;
        }));
    }

    /**
     * 将手机号添加到黑名单
     *
     * @param int $id 手机号ID
     * @return void
     */
    protected static function addActiveBlacklistIds(int $id): void
    {
        // 将手机号添加到Redis黑名单
        Redis::hset(self::PHONE_BLACKLIST_KEY, $id, now()->timestamp);
        Redis::expire(self::PHONE_BLACKLIST_KEY, self::BLACKLIST_EXPIRE_SECONDS);
        
        // 同时更新手机号状态为黑名单
        Phone::where('id', $id)->update(['status' => PhoneStatus::BLACKLIST]);
    }
    
   /**
     * 清理已过期的黑名单状态
     * 
     * @return Collection
     */
    public static function cleanExpiredBlacklist(): Collection
    {
        // 获取Redis中的有效黑名单
        $activeBlacklistIds = self::getActiveBlacklistIds();

        // 获取所有状态为黑名单的手机号
        $blacklistPhones = Phone::where('status', PhoneStatus::BLACKLIST)->whereNotIn('id',$activeBlacklistIds)->get();
        
        // 找出已经不在Redis黑名单中的手机号ID 将这些手机号状态恢复为正常
        return $blacklistPhones
            ->map(function(Phone $phone){
                $phone->update(['status' => PhoneStatus::NORMAL]);
                return $phone->id;
            });
    }
}
