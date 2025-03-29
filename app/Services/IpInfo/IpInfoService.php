<?php

namespace App\Services\IpInfo;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class IpInfoService
{
    /**
     * @param string|null $proxy
     * @param string $uri
     * @param int $times
     * @return PromiseInterface|Response
     */
    public function getIpInfo(?string $proxy = null, string $uri = 'http://api.ip.cc/',int $times = 5): PromiseInterface|Response
    {
        for ($i = 0; $i < $times; $i++) {
            try {
                return Http::retry(3, 100)->withHeaders([
                    'Accept'             => '*/*',
                    'Accept-Encoding'    => 'gzip, deflate, br, zstd',
                    'Accept-Language'    => 'en,zh-CN;q=0.9,zh;q=0.8',
                    'Connection'         => 'keep-alive',
                    'Sec-Ch-Ua'          => '"Not(A:Brand";v="99", "Google Chrome";v="133", "Chromium";v="133"',
                    'Sec-Ch-Ua-Mobile'   => '?0',
                    'Sec-Ch-Ua-Platform' => '"Windows"',
                    'Sec-Fetch-Dest'     => 'empty',
                    'Sec-Fetch-Mode'     => 'cors',
                    'Sec-Fetch-Site'     => 'same-site',
                    'User-Agent'         => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
                ])
                    ->timeout(10) // 设置超时时间
                    ->withOptions([
                        'proxy'  => $proxy,
                        'verify' => false, // 禁用SSL验证，在某些环境可能需要
                    ])
                    ->get($uri);
            } catch (ConnectionException) {

            }
        }

        throw new \RuntimeException('Failed to fetch IP information');
    }
}
