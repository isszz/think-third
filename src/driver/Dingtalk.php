<?php

namespace isszz\third\driver;

use isszz\third\Driver;
use isszz\third\interfaces\DriverInterface;
use isszz\third\User;

/**
 * “第三方个人应用”获取用户信息
 *
 * @see https://ding-doc.dingtalk.com/doc#/serverapi3/mrugr3
 *
 * 暂不支持“第三方企业应用”获取用户信息
 * @see https://ding-doc.dingtalk.com/doc#/serverapi3/hv357q
 */
class Dingtalk extends Driver implements DriverInterface
{
    protected string $getUserByCode = 'https://oapi.dingtalk.com/sns/getuserinfo_bycode';
    protected array $scopes = ['snsapi_login'];

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlBase('https://oapi.dingtalk.com/connect/qrconnect');
    }

    protected function getTokenUrl(): string
    {
        throw new \InvalidArgumentException('not supported to get access token.');
    }

    /**
     * @param string $token
     *
     * @return array
     */
    protected function getUserByToken(string $token): array
    {
        throw new \InvalidArgumentException('Unable to use token get User.');
    }

    /**
     * @param array $user
     *
     * @return \isszz\third\User
     */
    protected function mapUserToObject(array $user): User
    {
        return new User(
            [
                'name' => $user['nick'] ?? null,
                'nickname' => $user['nick'] ?? null,
                'id' => $user['openid'] ?? null,
                'email' => null,
                'avatar' => null,
            ]
        );
    }

    protected function getCodeFields(): array
    {
        return array_merge(
            [
                'appid' => $this->getAppId(),
                'response_type' => 'code',
                'scope' => implode($this->scopes),
                'redirect_uri' => $this->redirectUrl,
            ],
            $this->parameters
        );
    }

    protected function createSignature(int $time)
    {
        return base64_encode(hash_hmac('sha256', $time, $this->getSecret(), true));
    }

    /**
     * @param  string  $code
     *
     * @return User
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @see https://ding-doc.dingtalk.com/doc#/personnal/tmudue
     */
    public function userFromCode(string $code): User
    {
        $time = (int)microtime(true) * 1000;
        $queryParams = [
            'accessKey' => $this->getAppId(),
            'timestamp' => $time,
            'signature' => $this->createSignature($time),
        ];

        $response = $this->getHttpClient()->post(
            $this->getUserByCode . '?' . http_build_query($queryParams),
            [
                'json' => ['tmp_auth_code' => $code],
            ]
        );
        $response = \json_decode($response->getBody()->getContents(), true);

        if (0 != $response['errcode'] ?? 1) {
            throw new \InvalidArgumentException('You get error: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
        }
        
        return new User(
            [
                'name' => $response['user_info']['nick'],
                'nickname' => $response['user_info']['nick'],
                'id' => $response['user_info']['openid'],
            ]
        );
    }
}
