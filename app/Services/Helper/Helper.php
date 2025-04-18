<?php

namespace App\Services\Helper;

use Illuminate\Support\Facades\Http;
use Weijiajia\SaloonphpAppleClient\Exception\MaxRetryAttemptsException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
class Helper
{
    public static function parsePhoneCode(string $str): ?string
    {
        if (preg_match('/\b\d{6}\b/', $str, $matches)) {
            return $matches[0];
        }

        return null;
    }

     /**
     * Generate a secure random password with specific requirements
     *
     * @param int $minLength Minimum password length (must be at least 4)
     * @param int $maxLength Maximum password length
     * @return string The generated password
     * @throws \InvalidArgumentException If invalid length parameters are provided
     * @throws \Random\RandomException If random_int fails
     */
    public static function generatePassword(int $minLength = 8, int $maxLength = 20): string
    {
        if ($minLength < 8) {
            throw new \InvalidArgumentException('Minimum length must be at least 8 to accommodate required character types');
        }

        if ($minLength > $maxLength) {
            throw new \InvalidArgumentException('Minimum length cannot be greater than maximum length');
        }

        // Define character sets
        $charSets = [
            'uppercase' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'lowercase' => 'abcdefghijklmnopqrstuvwxyz',
            'numbers'   => '0123456789',
            'special'   => '!@#$%^&*()_+-=[]{}|;:,.<>?'
        ];

        // Determine password length
        $length = random_int($minLength, $maxLength);

        // Start with empty password
        $password = '';

        foreach ($charSets as $chars) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        // Create a combined character set for remaining characters
        $allChars = implode('', $charSets);
        $allCharsLength = strlen($allChars) - 1;

        while (strlen($password) < $length) {
            $nextChar = $allChars[random_int(0, $allCharsLength)];
            if ($nextChar !== substr($password, -1)) {
                $password .= $nextChar;
            }
        }

        // Shuffle the password to avoid predictable patterns
        return str_shuffle($password);
    }


    /**
     * @param string $uri
     * @param int $maxAttempts
     * @return string
     * @throws MaxRetryAttemptsException
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public static function attemptPhoneVerificationCode(string $uri, int $maxAttempts = 5): string
    {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            sleep($attempt * 3);

            $response = Http::retry(5, 100)->withoutVerifying()->get($uri);
            $code = Helper::parsePhoneCode($response->body());

            if ($code) {
                return $code;
            }
        }

        throw new MaxRetryAttemptsException("尝试 {$maxAttempts} 次后，获取手机验证码失败");
    }

    /**
     * @param string $email
     * @param string $uri
     * @param int $maxAttempts
     * @return string
     * @throws MaxRetryAttemptsException
     */
    public static function attemptEmailVerificationCode(string $email, string $uri, int $maxAttempts = 5): string
    {
        $isSuccess = false;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            sleep($attempt * 6);

            try {

                // 重试3次，每次100毫秒
                // 跳过 ssl 证书错误
                $response = Http::retry(5, 100)->withoutVerifying()->get($uri);

                if ($response->json('status') !== 1 && $response->json('statusCode') !== 200) {
                    continue;
                }

                $code = $response->json('message.email_code') ?: $response->json('data.code');

                if (empty($code)) {
                    continue;
                }

                $cacheCode = Cache::get($email);

                if ($cacheCode === $code) {
                    continue;
                }

                if (empty($cacheCode) && $isSuccess === false) {
                    $isSuccess = true;
                    continue;
                }

                return $code;
            } catch (\Illuminate\Http\Client\ConnectionException $e) {

            }
        }

        throw new MaxRetryAttemptsException("尝试 {$maxAttempts} 次后，获取邮箱验证码失败");
    }

    public static function getTimezone(string $timezone = 'America/Los_Angeles'): array
    {

        // "Sun Mar 23 2025 08:02:54 GMT-0700 (Pacific Daylight Time) (1742742174612)"

        // 创建指定时区的时间
        $now = Carbon::now($timezone);

        // 格式化为需要的格式
        $formatted = $now->format('D M d Y H:i:s');

        // 添加时区信息
        $timezone = "GMT{$now->format('O')} ({$now->getTimezone()->getName()})";

        // 输出结果
        return ['timezone' => sprintf('%s %s (%d)', $formatted, $timezone,$now->getPreciseTimestamp(3)), 'timezone_name' => $now->getTimezone()->getName()];
    }
}
