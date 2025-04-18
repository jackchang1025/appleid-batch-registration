<?php

namespace App\Services\Phone;

class Phone
{
    public function __construct(
        public string|int $id,
        public string $phone,
        public string $countryCode,
        public string $countryDialCode,
        public ?string $phoneAddress = null,
    ) {
    }

    public function id(): string|int
    {
        return $this->id;
    }

    public function phone(): string
    {
        return $this->phone;
    }

    public function countryCode(): string
    {
        return $this->countryCode;
    }

    public function countryDialCode(): string
    {
        return $this->countryDialCode;
    }

    public function phoneAddress(): ?string
    {
        return $this->phoneAddress;
    }
}