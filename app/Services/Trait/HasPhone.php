<?php

namespace App\Services\Trait;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Redis;
use App\Models\Phone;
use Illuminate\Support\Facades\DB;
use App\Enums\CountryEnum;
use App\Services\CountryLanguageService;
use App\Enums\PhoneStatus;
trait HasPhone
{
     // 添加类常量
     public const  PHONE_BLACKLIST_KEY = 'phone_code_blacklist';
     public const  BLACKLIST_EXPIRE_SECONDS = 3600; // 1小时过期

    protected ?Phone $phone = null;

    protected array $usedPhones = [];

    protected ?CountryLanguageService $country = null;

    /**
     * @return Phone
     */
    public function getPhone(): Phone
    {
        return DB::transaction(function () {

            // 获取有效黑名单ID
            // $blacklistIds = $this->getActiveBlacklistIds();
            $blacklistIds = [];

            $phone = Phone::query()
                ->where('status', PhoneStatus::NORMAL)
                ->whereNotNull(['phone_address', 'phone'])
                ->whereNotIn('id', $this->usedPhones)
                ->whereNotIn('id', $blacklistIds)
                ->when($this->country, function ($query) {
                    $query->where(function($subQuery) {
                        $subQuery->where('country_code_alpha3', $this->country->getAlpha3Code())
                                ->orWhere('country_code', $this->country->getAlpha2Code());
                    });
                })
                ->orderBy('id','desc')
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
    protected function getActiveBlacklistIds(): array
    {
        // 获取所有黑名单记录
        $blacklist = Redis::hgetall(self::PHONE_BLACKLIST_KEY);

        // 过滤出未过期的黑名单手机号ID
        return array_keys(array_filter($blacklist, function ($timestamp) {
            return (now()->timestamp - $timestamp) < self::BLACKLIST_EXPIRE_SECONDS;
        }));
    }

    protected function addActiveBlacklistIds(int $id): void
    {
        Redis::hset(self::PHONE_BLACKLIST_KEY, $id, now()->timestamp);
        Redis::expire(self::PHONE_BLACKLIST_KEY, self::BLACKLIST_EXPIRE_SECONDS);
    }
}
