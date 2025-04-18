<?php

namespace App\Services\Phone;

class PhoneDepositoryFacroty
{
    public function make(string $repository): PhoneDepository
    {
        return match ($repository) {
            'database' => app()->make(DatabasePhone::class),
            'five_sim' => app()->make(FiveSimPhone::class),
            default => throw new \InvalidArgumentException('Invalid phone repository'),
        };
    }
}