<?php

namespace isszz\third\driver;

use isszz\third\Driver;
use isszz\third\interfaces\DriverInterface;
use isszz\third\exception\AuthorizeFailedException;
use isszz\third\User;

class Qq extends Driver implements DriverInterface
{
    /**
     * The base url of QQ API.
     */
    protected string $baseUrl = 'https://graph.qq.com';

    /**
     * get token(openid) with unionid.
     *
     * @var bool
     */
    protected bool $withUnionId = false;

    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected array $scopes = ['get_user_info'];

    /**
     * Get the authentication URL for the provider.
     *
     * @param string $state
     *
     * @return string
     */
    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlBase($this->baseUrl.'/oauth2.0/authorize');
    }

    /**
     * Get the token URL for the provider.
     *
     * @return string
     */
    protected function getTokenUrl(): string
    {
        return $this->baseUrl .'/oauth2.0/token';
    }

    /**
     * Get the Post fields for the token request.
     *
     * @param string $code
     *
     * @return array
     */
    protected function getTokenFields(string $code): array
    {
        return parent::getTokenFields($code) + ['grant_type' => 'authorization_code'];
    }

    /**
     * @param  string  $code
     *
     * @return array
     * @throws \isszz\third\Exception\AuthorizeFailedException | \GuzzleHttp\Exception\GuzzleException
     */
    public function getAccessToken(string $code): array
    {
        $response = $this->getHttpClient()->get($this->getTokenUrl(), [
            'query' => $this->getTokenFields($code),
        ]);

        $response = $response->getBody()->getContents();

        if($response != '' && stripos($response, 'callback') !== false) {
            preg_match('/callback\(\s+(.*?)\s+\)/i', $response, $responseArray);
            $response = json_decode($responseArray[1], true);
            throw new AuthorizeFailedException('Authorize Failed: ' . json_encode($response, JSON_UNESCAPED_UNICODE), $response);
        }

        \parse_str($response, $token);

        return $this->normalizeAccessTokenResponse($token);
    }

    /**
     * @return self
     */
    public function withUnionId(): self
    {
        $this->withUnionId = true;

        return $this;
    }

    /**
     * Get the raw user for the given access token.
     *
     * @param string $token
     *
     * @return array
     */
    protected function getUserByToken(string $token): array
    {
        $url = $this->baseUrl.'/oauth2.0/me?access_token='. $token;

        $this->withUnionId && $url .= '&unionid=1';

        $response = $this->getHttpClient()->get($url);

        $me = \json_decode($response->getBody()->getContents(), true);

        $queries = [
            'access_token' => $token,
            'fmt' => 'json',
            'openid' => $me['openid'],
            'oauth_consumer_key' => $this->getAppId(),
        ];

        $response = $this->getHttpClient()->get($this->baseUrl .'/user/get_user_info?'. http_build_query($queries));

        return (\json_decode($response->getBody()->getContents(), true) ?? []) + [
            'unionid' => $me['unionid'] ?? null,
            'openid' => $me['openid'] ?? null,
        ];
    }

    /**
     * Map the raw user array to a Socialite User instance.
     *
     * @param array $user
     *
     * @return \isszz\third\User
     */
    protected function mapUserToObject(array $user): User
    {
        return new User([
            'id' => $user['openid'] ?? null,
            'name' => $user['nickname'] ?? null,
            'nickname' => $user['nickname'] ?? null,
            'email' => $user['email'] ?? null,
            'avatar' => $user['figureurl_qq_2'] ?? null,
        ]);
    }
}
