<?php

namespace isszz\third\driver;

use isszz\third\Driver;
use isszz\third\interfaces\DriverInterface;
use isszz\third\Exception\AuthorizeFailedException;
use isszz\third\User;

class Gitee extends Driver implements DriverInterface
{
    protected string $baseUrl = 'https://gitee.com';
    
    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlBase($this->baseUrl .'/oauth/authorize');
    }

    protected function getTokenUrl(): string
    {
        return $this->baseUrl .'/oauth/token?';
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
     * @param  string  $token
     *
     * @return array
     * @throws \isszz\third\Exception\AuthorizeFailedException | \GuzzleHttp\Exception\GuzzleException
     */
    protected function getUserByToken(string $token): array
    {
        $params = [
            'access_token' => $token
        ];

        $response = $this->getHttpClient()->get($this->baseUrl .'/api/v5/user?'. http_build_query($params));

        $response = \json_decode($response->getBody()->getContents(), true) ?? [];

		if (!empty($response['message'])) {
		    throw new AuthorizeFailedException($response['message'], $response);
        }

        return $response;
    }

    protected function mapUserToObject(array $user): User
    {
        return new User([
            'id' => $user['id'] ?? null,
            'nickname' => $user['name'] ?? null,
            'name' => $user['login'] ?? null,
            'email' => $user['email'] ?? null,
            'avatar' => $user['avatar_url'] ?? null,
        ]);
    }
}
