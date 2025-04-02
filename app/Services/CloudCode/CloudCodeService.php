<?php

namespace App\Services\CloudCode;

use Psr\Log\LoggerInterface;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Weijiajia\DecryptVerificationCode\CloudCode\CloudCodeConnector;
use Weijiajia\DecryptVerificationCode\CloudCodeResponseInterface;
use Weijiajia\DecryptVerificationCode\Exception\DecryptCloudCodeException;

class CloudCodeService {

    /**
     * 构造函数
     *
     * @param string $token
     * @param CloudCodeConnector $connector
     * @param LoggerInterface|null $logger
     * @param string|null $type
     * @param bool $debug
     */
    public function __construct(
        protected string $token,
        protected CloudCodeConnector $connector = new CloudCodeConnector(),
        protected ?LoggerInterface $logger = null,
        protected ?string $type = null,
        protected bool $debug = false,
    )
    {
        $this->connector->withLogger($this->logger);
        $this->debug && $this->connector->debug();
    }

    public function cloudCodeConnector(): CloudCodeConnector
    {
        return $this->connector;
    }


    /**
     * 解密验证码
     *
     * @param string $image 验证码图片
     * @param string|null $type 验证码类型
     * @param bool|null $debug
     * @return CloudCodeResponseInterface
     * @throws \JsonException
     * @throws FatalRequestException
     * @throws RequestException
     * @throws DecryptCloudCodeException
     */
    public function resolveCaptcha(string $image, ?string $type = null): CloudCodeResponseInterface
    {
        $type = $type ?: $this->type;

        return $this->connector->decryptCloudCode(
            token: $this->token,
            type: $type,
            image: $image,
        );
    }
}
