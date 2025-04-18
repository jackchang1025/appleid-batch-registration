<?php

namespace App\Services\Phone;

use Illuminate\Support\Facades\DB;
use App\Models\Phone as PhoneModel;
use App\Enums\PhoneStatus;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;
use App\Services\Integrations\Phone\PhoneConnector;
use Psr\Log\LoggerInterface;
use libphonenumber\PhoneNumberFormat;
use App\Services\CountryLanguageService;

class DatabasePhone implements PhoneDepository
{
    protected array $usedPhones = [];

     // 添加类常量
     public const PHONE_BLACKLIST_KEY = 'phone_code_blacklist';
     public const BLACKLIST_EXPIRE_SECONDS = 3600; // 1小时过期

     protected ?PhoneConnector $phoneConnector = null;

    public function __construct(
        protected LoggerInterface $logger,
    ) 
    {

            $this->phoneConnector = new PhoneConnector();
            $this->phoneConnector->withLogger($this->logger);
            $this->phoneConnector->debug();
    }

    public function connect(): PhoneConnector
    {
        return $this->phoneConnector;
    }

    public function getPhone(CountryLanguageService $country): Phone
    {
        return DB::transaction(function () use ($country) {
            // 获取有效黑名单ID
            $blacklistIds = self::getActiveBlacklistIds();

            // 创建基本查询
            $query = PhoneModel::query()
                    ->where('status', PhoneStatus::NORMAL)
                    ->whereNotNull(['phone_address', 'phone']);

            // 只有在数组不为空时才添加 whereNotIn 条件
            if (!empty($this->usedPhones)) {
                $query->whereNotIn('id', $this->usedPhones);
            }

            if (!empty($blacklistIds)) {
                $query->whereNotIn('id', $blacklistIds);
            }
            
            // 如果国家条件存在，直接添加国家筛选条件
            if ($country) {
                $query->where(fn($subQuery) => $subQuery->where('country_code_alpha3', $country->getAlpha3Code())->orWhere('country_code', $country->getAlpha2Code()));
            }
            
            // 使用 FOR UPDATE SKIP LOCKED 安全地锁定一行记录
            // 这样即使在高并发下也不会导致整个表被锁
            $sql = $query->orderBy('id', 'desc')
                ->limit(1)
                ->toSql();
                
            // 手动添加 SKIP LOCKED 子句
            $sql .= " FOR UPDATE SKIP LOCKED";
            
            $bindings = $query->getBindings();
            
            // 执行优化的SQL语句获取一个可用的手机号记录
            $result = DB::select($sql, $bindings);
            
            if (empty($result)) {
                throw new ModelNotFoundException('没有可用的手机号');
            }
            
            // 将结果转换为Phone模型
            $phoneData = (array)$result[0];
            $phone = PhoneModel::find($phoneData['id']);
            
            // 更新手机号状态为绑定中
            $phone->update(['status' => PhoneStatus::BINDING]);
            
            // 记录已使用的手机号
            $this->usedPhones[] = $phone->id;
            
            return new Phone(
                id: $phone->id,
                phone: $phone->getPhoneNumberService()->format(PhoneNumberFormat::NATIONAL),
                countryCode: $phone->country_code,
                countryDialCode: $phone->country_dial_code,
                phoneAddress: $phone->phone_address,
            );
        });
    }

    public function getPhoneCode(Phone $phone): string
    {
        return $this->connect()->attemptGetPhoneCode($phone->phone(), $phone->phoneAddress());
    }

    public function canPhone(Phone $phone)
    {
        return PhoneModel::where('phone', $phone->phone())->update(['status' => PhoneStatus::NORMAL]);
    }

    public function banPhone(Phone $phone)
    {
        self::addActiveBlacklistIds($phone->id());
    }

    public function finishPhone(Phone $phone)
    {
        return PhoneModel::where('phone', $phone->phone())->update(['status' => PhoneStatus::BOUND]);
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
        PhoneModel::where('id', $id)->update(['status' => PhoneStatus::BLACKLIST]);
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
        $blacklistPhones = PhoneModel::where('status', PhoneStatus::BLACKLIST)->whereNotIn('id',$activeBlacklistIds)->get();
        
        // 找出已经不在Redis黑名单中的手机号ID 将这些手机号状态恢复为正常
        return $blacklistPhones
            ->map(function(PhoneModel $phone){
                $phone->update(['status' => PhoneStatus::NORMAL]);
                return $phone->id;
            });
    }
    
}
