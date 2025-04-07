<?php

namespace App\Models;

use App\Enums\PhoneStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\Phone\PhoneService;
use App\Services\Phone\PhoneNumberFactory;


/**
 *
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Phone newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Phone newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Phone query()
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $phone 手机号
 * @property string $phone_address 手机号地址
 * @property string $country_code 国家码
 * @property string $country_dial_code 区号
 * @property string $national_number 获取不包含国家代码的号码
 * @property string $status {normal:正常,invalid:失效,bound:已绑定}
 * @method static \Illuminate\Database\Eloquent\Builder|Phone whereCountryCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Phone whereCountryDialCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Phone whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Phone whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Phone wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Phone wherePhoneAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Phone whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Phone whereUpdatedAt($value)
 * @property-read mixed $label
 * @method static \Database\Factories\PhoneFactory factory($count = null, $state = [])
 * @property string|null $country_code_alpha3
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Phone whereCountryCodeAlpha3($value)
 * @mixin \Eloquent
 */
class Phone extends Model
{
    use HasFactory;


    protected $fillable = ['phone','phone_address','country_code','country_dial_code','status','country_code_alpha3'];

    protected $casts = [
        'status' => PhoneStatus::class,
    ];

    protected function countryDialCode(): Attribute
    {
        return Attribute::make(
            set: function (?string $value, array $attributes) {
                return $this->getPhoneNumberService($attributes['phone'], $attributes['country_code'])->getCountryCode(
                );
            }
        );
    }

    protected function nationalNumber(): Attribute
    {
        return Attribute::make(
            get: function (?string $value, array $attributes) {
                return $this->getPhoneNumberService(
                    $attributes['phone'],
                    $attributes['country_code']
                )->getNationalNumber();
            }
        );
    }

    protected function countryCode(): Attribute
    {
        return Attribute::make(
            set: function (?string $value, array $attributes) {
                return $this->getPhoneNumberService($attributes['phone'], $value)->getCountry();
            }
        );
    }

    protected function countryCodeAlpha3(): Attribute
    {
        return Attribute::make(
            set: function (?string $value, array $attributes) {
                return $this->getPhoneNumberService($attributes['phone'])->getCountryCodeAlpha3();
            }
        );
    }

    /**
     * 获取 PhoneNumberService 实例
     *
     * @param string $phone
     * @param string|null $countryCode
     * @return PhoneService
     */
    public function getPhoneNumberService(?string $countryCode = null): PhoneService
    {
        return app(PhoneNumberFactory::class)->create($this->phone, [$countryCode]);
    }

    public function getCountryCode(): string
    {
        return $this->country_code;
    }

    public function getPhoneNumber(): string
    {
        return $this->phone;
    }

    public function getCountryDialCode(): string
    {
        return $this->country_dial_code;
    }

    public function getPhoneAddress(): string
    {
        return $this->phone_address;
    }
}
