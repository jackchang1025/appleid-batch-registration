<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Weijiajia\SaloonphpAppleClient\Contracts\AppleIdInterface;
/**
 *
 *
 * @property int $id
 * @property string $email
 * @property string $email_uri
 * @property string $phone
 * @property string $phone_uri
 * @property string $phone_country_code
 * @property string $phone_country_dial_code
 * @property string $password
 * @property string $first_name
 * @property string $last_name
 * @property string|null $country
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appleid newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appleid newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appleid query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appleid whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appleid whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appleid whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appleid whereEmailUri($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appleid whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appleid whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appleid whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appleid wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appleid wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appleid wherePhoneCountryCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appleid wherePhoneCountryDialCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appleid wherePhoneUri($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appleid whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Appleid extends Model implements AppleIdInterface
{
    protected $fillable = ['email', 'email_uri', 'phone','phone_uri','password','first_name','last_name','country','phone_country_code','phone_country_dial_code'];

    public function getAppleId(): string
    {
        return $this->email;
    }

    public function getEmailUri(): ?string
    {
        return $this->email_uri;
    }

    public function getPhoneUri(): ?string
    {
        return $this->phone_uri;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }


}
