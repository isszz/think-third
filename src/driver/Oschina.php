<?php

namespace isszz\third\driver;

use isszz\third\Driver;
use isszz\third\interfaces\DriverInterface;
use isszz\third\User;

class Oschina extends Driver implements DriverInterface
{
    protected string $baseUrl = 'https://www.oschina.net/action';
    
    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlBase($this->baseUrl .'/oauth2/authorize');
    }

    protected function getTokenUrl(): string
    {
        return $this->baseUrl .'/openapi/token?';
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

        // \parse_str($response->getBody()->getContents(), $token);

        return $this->normalizeAccessTokenResponse($response->getBody()->getContents());
    }

    /**
     * @param  string  $token
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function getUserByToken(string $token): array
    {
        $params = [
            'access_token' => $token,
			'dataType' => 'json'
        ];

        $response = $this->getHttpClient()->get($this->baseUrl .'/openapi/user?'. http_build_query($params));

        return \json_decode($response->getBody()->getContents(), true) ?? [];
    }

    protected function mapUserToObject(array $user): User
    {
        return new User([
            'id' => $user['id'] ?? null,
            'nickname' => $user['name'] ?? null,
            'name' => $user['name'] ?? null,
            'email' => $user['email'] ?? null,
            'avatar' => $user['avatar'] ?? null,
        ]);
    }
}