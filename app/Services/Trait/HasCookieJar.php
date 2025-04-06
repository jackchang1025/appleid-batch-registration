<?php

namespace App\Services\Trait;

use GuzzleHttp\Cookie\CookieJar;

trait HasCookieJar
{
    protected ?CookieJar $cookieJar = null;

    public function cookieJar(): CookieJar
    {
        return $this->cookieJar ??= new CookieJar();
    }
}