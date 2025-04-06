<?php

namespace App\Services\Trait;

use Weijiajia\SaloonphpAppleClient\Integrations\AppleAuthenticationConnector\Dto\Request\SignInComplete;
use Weijiajia\SaloonphpAppleClient\Integrations\Idmsa\Dto\Request\SignIn\SignInComplete as IdmsaSignInComplete;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Weijiajia\SaloonphpAppleClient\Integrations\Idmsa\Dto\Response\SignIn\SignInComplete as SignInCompleteResponse;
use Weijiajia\SaloonphpAppleClient\Exception\SignInException;
use Weijiajia\SaloonphpAppleClient\Integrations\Idmsa\Dto\Response\Auth\Auth as AppleAuth;
use Weijiajia\SaloonphpAppleClient\Integrations\AppleAuthenticationConnector\AppleAuthenticationConnector;
use Weijiajia\SaloonphpAppleClient\Integrations\Idmsa\IdmsaConnector;

trait HasSignIn
{

    protected ?AppleAuth $appleAuth = null;

    abstract public function appleAuthenticationConnector(): AppleAuthenticationConnector;

    abstract public function idmsaConnector(): IdmsaConnector;

    /**
     * @param string $appleId
     * @param string $password
     * @return SignInCompleteResponse
     * @throws FatalRequestException
     * @throws RequestException
     * @throws SignInException
     * @throws \JsonException
     */
        public function signIn(string $appleId,string $password): SignInCompleteResponse
        {
            $initData = $this->appleAuthenticationConnector()
                ->getAuthenticationResource()
                ->signInInit($appleId);

            $signInInitData = $this->idmsaConnector()
                ->getAuthenticateResources()
                ->signInInit(a: $initData->value, account: $appleId);

            $completeResponse = $this->appleAuthenticationConnector()
                ->getAuthenticationResource()
                ->signInComplete(
                    SignInComplete::from(
                        [
                            'key'       => $initData->key,
                            'salt'      => $signInInitData->salt,
                            'b'         => $signInInitData->b,
                            'c'         => $signInInitData->c,
                            'password'  => $password,
                            'iteration' => $signInInitData->iteration,
                            'protocol'  => $signInInitData->protocol,
                        ]
                    )
                );

            return $this->idmsaConnector()
                ->getAuthenticateResources()
                ->signInComplete(
                    IdmsaSignInComplete::from([
                        'account' => $appleId,
                        'm1'      => $completeResponse->M1,
                        'm2'      => $completeResponse->M2,
                        'c'       => $completeResponse->c,
                    ])
                );
        }

    /**
     * @return AppleAuth
     * @throws FatalRequestException
     * @throws RequestException
     */
    public function getAppleAuth(): AppleAuth
    {
        return $this->appleAuth ??= $this->idmsaConnector()->getAuthenticateResources()->auth();
    }
}
