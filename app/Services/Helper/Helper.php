<?php

namespace App\Services\Helper;

use Illuminate\Support\Facades\Http;
use Weijiajia\SaloonphpAppleClient\Exception\MaxRetryAttemptsException;
use Illuminate\Support\Facades\Cache;
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
        // Validate input parameters
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

        // Add one character from each required set
        foreach ($charSets as $type => $chars) {
            $randomIndex = random_int(0, strlen($chars) - 1);
            $password .= $chars[$randomIndex];
        }

        // Create a combined character set for remaining characters
        $allChars = implode('', $charSets);
        $allCharsLength = strlen($allChars) - 1;

        // Fill the remaining length with random characters
        $remainingLength = $length - count($charSets);
        for ($i = 0; $i < $remainingLength; $i++) {
            $password .= $allChars[random_int(0, $allCharsLength)];
        }

        // Shuffle the password to avoid predictable patterns
        return str_shuffle($password);
    }

    
    public static function getPhoneVerificationCode(string $uri, int $maxAttempts = 5): string
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

    public static function getEmailVerificationCode(string $email, string $uri, int $maxAttempts = 5): string
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
}   