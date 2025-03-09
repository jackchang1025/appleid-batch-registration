<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appleid extends Model
{
    protected $fillable = ['email', 'email_uri', 'phone','phone_uri','password','first_name','last_name','country','phone_country_code','phone_country_dial_code'];
}
