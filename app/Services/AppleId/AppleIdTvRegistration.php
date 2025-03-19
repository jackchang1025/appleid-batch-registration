<?php

namespace App\Services\AppleId;

use App\Models\Appleid;
use App\Models\Email;
use App\Services\Helper\Helper;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\MessageFormatter;
use Psr\Log\LoggerInterface;
use Weijiajia\HttpProxyManager\ProxyManager;
use Weijiajia\SaloonphpAppleClient\Exception\AccountException;
use Weijiajia\SaloonphpAppleClient\Exception\CreateAccountException;
use Weijiajia\SaloonphpAppleClient\Exception\RegistrationException;
use Weijiajia\SaloonphpAppleClient\Integrations\AuthTvApple\AuthTvAppleConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\BuyTvApple\BuyTvAppleConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\BuyTvApple\Data\CreateAccountSrvData;
use Weijiajia\SaloonphpAppleClient\Integrations\BuyTvApple\Data\ValidateAccountFieldsSrvData;
use Weijiajia\SaloonphpAppleClient\Integrations\TvApple\TvAppleConnector;
use Weijiajia\SaloonphpHttpProxyPlugin\ProxySplQueue;
use Illuminate\Support\Collection;
use Weijiajia\HttpProxyManager\Contracts\ProxyInterface;
use Weijiajia\HttpProxyManager\ProxyConnector;
use Weijiajia\SaloonphpAppleClient\Exception\AccountAlreadyExistsException;
use App\Enums\EmailStatus;
use GuzzleHttp\Cookie\SetCookie;
use Saloon\Http\Connector;
use Weijiajia\SaloonphpAppleClient\Exception\MaxRetryAttemptsException;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\BuyTvApple\Data\ValidateEmailConfirmationCodeSrvResponse;
use Weijiajia\SaloonphpAppleClient\Exception\VerificationCodeException;
use Psr\Http\Message\RequestInterface;

class AppleIdTvRegistration
{
    private const LOG_FORMAT = <<<FORMAT
{method} {uri} HTTP/{version}
proxy: {proxy}
{req_headers}
{req_body}
----------
HTTP/{version} {code} {phrase}
{res_headers}
{res_body}
FORMAT;

    private ?FileCookieJar $cookieJar = null;
    private  ProxySplQueue $queue;
    private  TvAppleConnector $tvAppleConnector;
    private  AuthTvAppleConnector $authTvAppleConnector;
    private  BuyTvAppleConnector $buyTvAppleConnector;
    private  ProxyConnector $proxyConnector;


    private  string $cookieJarPath;
    private  string $password;
    private  string $firstName;
    private  string $lastName;
    private  int $birthMonth;
    private  int $birthDay;
    private  int $birthYear;

    private  ?string $code = null;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ProxyManager $proxyManager
    ) {

        // 生成随机个人信息
        $this->password = Helper::generatePassword();
        $this->firstName = fake()->firstName();
        $this->lastName = fake()->lastName();
        $this->birthMonth = fake()->month();
        $this->birthDay = fake()->dayOfMonth($this->birthMonth);
        $this->birthYear = (int) date('Y', random_int(strtotime('1950-01-01'), strtotime('2000-12-31')));

        // 初始化队列
        $this->queue = new ProxySplQueue();

        $this->proxyConnector = $this->proxyManager->driver();
        $this->proxyConnector->debug();
    }

    /**
     * @param string $encodedContent
     * @return array|null
     * @throws \JsonException
     */
    private function decodedContent(string $encodedContent): ?array
    {
        return json_decode(urldecode($encodedContent), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * 初始化Cookie
     */
    private function getCookieJar(): void
    {
        if($this->cookieJar === null){
            // 确保Cookie目录存在
            $cookieDir = dirname($this->cookieJarPath);
            if (!is_dir($cookieDir) && !mkdir($cookieDir, 0777, true) && !is_dir($cookieDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $cookieDir));
            }

            file_exists($this->cookieJarPath) && unlink($this->cookieJarPath);
            $this->cookieJar = new FileCookieJar($this->cookieJarPath);
        }

    }

    /**
     * 设置代理
     */
    private function setupProxy(): void
    {
        $proxy = $this->proxyConnector->default();

        if ($proxy instanceof Collection) {

            $proxy->each(fn(ProxyInterface $item) => $this->queue->enqueue($item->getUrl()));

        } else {
            $this->queue->enqueue($proxy->getUrl());
        }

    }

    /**
     * 添加日志中间件到连接器
     */
    private function addLoggerMiddleware(Connector $connector): void
    {
        $logger = $this->logger;
        $messageFormatter = new MessageFormatter(self::LOG_FORMAT);

        // 自定义日志中间件
        $connector->sender()->addMiddleware(function (callable $handler) use ($logger, $messageFormatter) {
            return function (RequestInterface $request, array $options) use ($handler, $logger, $messageFormatter) {
                return $handler($request, $options)->then(
                    function ($response) use ($logger, $request, $messageFormatter, $options) {

                        // 格式化消息
                        $message = $messageFormatter->format($request, $response);

                        // 替换代理占位符
                        $message = str_replace('proxy', 'proxy:'.($options['proxy'] ?: ''), $message);

                        $logger->info($message);
                        return $response;
                    },
                    function ($reason) use ($logger, $request, $messageFormatter, $options) {
                        $response = $reason instanceof RequestException
                            ? $reason->getResponse()
                            : null;

                        $message = $messageFormatter->format($request, $response, $reason);
                        $message = str_replace('proxy', 'proxy:'.($options['proxy'] ?: ''), $message);

                        $logger->error($message);
                        return \GuzzleHttp\Promise\Create::rejectionFor($reason);
                    }
                );
            };
        });
    }

    /**
     * 初始化连接器的通用设置
     */
    private function initConnector(AppleConnector $connector): void
    {
        // 设置代理队列
        $connector->withSplQueue($this->queue);

        // 设置轮换模式
        $connector->roundRobin();

        // 强制使用代理
        $connector->withForceProxy(true);
        // 设置cookie
        $connector->withCookies($this->cookieJar);
        // 设置重试次数
        $connector->tries = 3;
        // 设置重试间隔
        $connector->retryInterval = 1000;
        // 设置调试
        $connector->debug();
    }

    /**
     * @param Email $email
     * @return Appleid
     * @throws AccountAlreadyExistsException
     * @throws MaxRetryAttemptsException
     * @throws RegistrationException
     * @throws VerificationCodeException
     * @throws \JsonException
     * @throws \Throwable
     * @throws AccountException
     * @throws CreateAccountException
     */
    public function run(Email $email): Appleid
    {

       try{

            $email->update([
                'status' => EmailStatus::PROCESSING,
            ]);

            // 设置Cookie路径
            $this->cookieJarPath = storage_path("/app/public/cookies/{$email->email}.json");

            $this->getCookieJar();
            $this->setupProxy();

            $this->tvAppleConnector = new TvAppleConnector();
            $this->initConnector($this->tvAppleConnector);

            $geo = new SetCookie([
                'Name' => 'geo',
                'Value' => 'US',
                'Domain' => '.apple.com',
                'Path' => '/',
                'Secure' => false,
                'HttpOnly' => false,
            ]);

            $site = new SetCookie([
                'Name' => 'site',
                'Value' => 'USA',
                'Domain' => '.apple.com',
                'Path' => '/',
                'Secure' => true,
                'HttpOnly' => true,
            ]);

            $dslang = new SetCookie([
                'Name' => 'dslang',
                'Value' => 'US-EN',
                'Domain' => '.apple.com',
                'Path' => '/',
                'Secure' => true,
                'HttpOnly' => true,
            ]);

            $this->cookieJar->setCookie($geo);
            $this->cookieJar->setCookie($site);
            $this->cookieJar->setCookie($dslang);

            $this->authTvAppleConnector = new AuthTvAppleConnector();
            $this->initConnector($this->authTvAppleConnector);
            $this->addLoggerMiddleware($this->authTvAppleConnector);

            $this->buyTvAppleConnector = new BuyTvAppleConnector();
            $this->initConnector($this->buyTvAppleConnector);
            $this->addLoggerMiddleware($this->buyTvAppleConnector);

            $token = $this->getResourcesAndToken();
//            $token = 'eyJhbGciOiJFUzI1NiIsInR5cCI6IkpXVCIsImtpZCI6IldlYlBsYXlLaWQifQ.eyJpc3MiOiJBTVBXZWJQbGF5IiwiaWF0IjoxNzQxNzI0OTk2LCJleHAiOjE3NTcyNzY5OTYsInJvb3RfaHR0cHNfb3JpZ2luIjpbImFwcGxlLmNvbSJdfQ.i_WhpwafmgxICuONMXJ53rrBoNsK7jZGHsedk4ioocywCC7LZGYMT1DZnM-1wmwqUa9yWFdepV4ErgZcBYG5Hg';

            $email->createLog('获取资源和令牌',['token' => $token]);

            $initializeSessionResponse = $this->authTvAppleConnector
                ->getResources()
                ->getInitializeSession();

            $email->createLog('获取初始化会话',$initializeSessionResponse->toArray());


            $response = $this->authTvAppleConnector
                ->getResources()
                ->getAccountNameValidate($email->email,$initializeSessionResponse->pageUUID);

            $email->createLog('账号验证',$response->toArray());

            if($response->accountNameAvailable === false){
                throw new AccountAlreadyExistsException($response->getResponse()->body());
            }

            $podResponse = $this->buyTvAppleConnector->getResources()->pod();

            $email->createLog('获取 pod',['response' => $podResponse->json()]);

            $createOptionsResponse = $this->buyTvAppleConnector->getResources()->createOptions();

            $email->createLog('获取创建选项',$createOptionsResponse->toArray());

            $data = ValidateAccountFieldsSrvData::from([
                'email' => $email->email,
                'acAccountName' => $email->email,
                'firstName' => $this->firstName,
                'lastName' => $this->lastName,
                'birthMonth' => $this->birthMonth,
                'birthDay' => $this->birthDay,
                'birthYear' => $this->birthYear,
                'acAccountPassword' => $this->password,
                'pageUUID' => $createOptionsResponse->pageUUID,
            ]);

            $validateAccountFieldsSrvResponse = $this->buyTvAppleConnector
                ->getResources()
                ->validateAccountFieldsSrv($data);

            $email->createLog('验证账号字段',$validateAccountFieldsSrvResponse->toArray());

            $generateEmailConfirmationCodeSrvResponse = $this->buyTvAppleConnector
                ->getResources()
                ->generateEmailConfirmationCodeSrv($email->email);

            $email->createLog('生成邮箱验证码',$generateEmailConfirmationCodeSrvResponse->toArray());

            $validateEmailConfirmationCodeSrvResponse = $this->attemptsVerifyEmail($email,$generateEmailConfirmationCodeSrvResponse->clientToken);

            $data = CreateAccountSrvData::from([
                'email' => $email->email,
                'firstName' => $this->firstName,
                'lastName' => $this->lastName,
                'birthMonth' => $this->birthMonth,
                'birthDay' => $this->birthDay,
                'birthYear' => $this->birthYear,
                'acAccountName' => $email->email,
                'acAccountPassword' => $this->password,
                'pageUUID' => $validateEmailConfirmationCodeSrvResponse->pageUUID,
                'secretCode' => $this->code,
                'clientToken' => $validateEmailConfirmationCodeSrvResponse->clientToken,
            ]);

            $createAccountSrvResponse = $this->buyTvAppleConnector->getResources()->createAccountSrv($token, $data);

            $email->createLog('创建账号数据',$createAccountSrvResponse->toArray());

            $email->update([
                'status' => EmailStatus::REGISTERED,
            ]);

            return Appleid::create([
                'email'                   => $email->email,
                'email_uri'               => $email->email_uri,
                'password'                => $this->password,
                'first_name'              => $this->firstName,
                'last_name'               => $this->lastName,
            ]);

       }catch(AccountAlreadyExistsException $e){
            $email->update([
                'status' => EmailStatus::REGISTERED,
            ]);

            $email->createLog("账号已注册",['message' => $e->getMessage()]);
            throw $e;

       }catch(\Throwable $e){
            $email->update([
                'status' => EmailStatus::FAILED,
            ]);

            $email->createLog("注册失败",['message' => $e->getMessage()]);
            throw $e;
       }
    }

    /**
     * @param Email $email
     * @param string $clientToken
     * @param int $attempts
     * @return ValidateEmailConfirmationCodeSrvResponse
     * @throws MaxRetryAttemptsException
     * @throws RegistrationException
     */
    protected function attemptsVerifyEmail(Email $email,string $clientToken,int $attempts = 5): ValidateEmailConfirmationCodeSrvResponse
    {

        for($i = 0; $i < $attempts; $i++){

            try{

                $this->code = Helper::getEmailVerificationCode($email->email, $email->email_uri);

                $email->createLog('获取邮箱验证码',['code' => $this->code]);

                $validateEmailConfirmationCodeSrvResponse = $this->buyTvAppleConnector->getResources()->validateEmailConfirmationCodeSrv(
                    email: $email->email,
                    clientToken: $clientToken,
                    secretCode: $this->code
                );

                $email->createLog('验证邮箱验证码',$validateEmailConfirmationCodeSrvResponse->toArray());

                return $validateEmailConfirmationCodeSrvResponse;

            }catch(VerificationCodeException $e){

                $email->createLog('验证邮箱验证码失败',['message' => $e->getMessage()]);

            }
        }

        throw new MaxRetryAttemptsException(" {$attempts} 次验证邮箱验证码失败");
    }

    /**
     * 获取资源和令牌
     */
    private function getResourcesAndToken(): ?string
    {

        $response = $this->tvAppleConnector->getResources()->getTvApple();

        $meta = $response->dom()->filter('meta[name="web-tv-app/config/environment"]');
        $content = $meta->attr('content');

        if(empty($content)){
            throw new \RuntimeException('content is empty');
        }

        $token = data_get($this->decodedContent($content), 'MEDIA_API.token');
        if(empty($token)){
            throw new \RuntimeException('token is empty');
        }

        return $token;
    }
}
