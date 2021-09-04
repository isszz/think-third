<?php

namespace isszz\third\driver;

use isszz\third\Driver;
use isszz\third\interfaces\DriverInterface;
use isszz\third\User;

class Baidu extends Driver implements DriverInterface
{
    /**
     * The base url of QQ API.
     */
    protected string $baseUrl = 'https://openapi.baidu.com';

    /**
     * The version.
     *
     * @var string
     */
    protected string $version = '2.0';

    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected array $scopes = ['basic'];
    
    /**
     * The display.
     *
     * @var string
     */
    protected string $display = 'popup';

    /**
     * @param string $display
     *
     * @return $this
     */
    public function withDisplay(string $display): self
    {
        $this->display = $display;

        return $this;
    }

    /**
     * @param array $scopes
     *
     * @return self
     */
    public function withScopes(array $scopes): self
    {
        $this->scopes = $scopes;

        return $this;
    }

    /**
     * Get the authentication URL for the provider.
     *
     * @param string $state
     *
     * @return string
     */
    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlBase($this->baseUrl . '/oauth/' . $this->version . '/authorize');
    }

    /**
     * Get the token URL for the provider.
     *
     * @return string
     */
    protected function getTokenUrl(): string
    {
        return $this->baseUrl .'/oauth/' . $this->version . '/token';
    }

    protected function getCodeFields(): array
    {
        return [
            'response_type' => 'code',
            'client_id' => $this->getAppId(),
            'redirect_uri' => $this->redirectUrl,
            'scope' => $this->formatScopes($this->scopes, $this->scopeSeparator),
            'display' => $this->display,
        ] + $this->parameters;
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
     * Get the raw user for the given access token.
     *
     * @param string $token
     *
     * @return array
     */
    protected function getUserByToken(string $token): array
    {
        $response = $this->getHttpClient()->get(
            $this->baseUrl . '/rest/' . $this->version . '/passport/users/getInfo',
            [
                'query' => [
                    'access_token' => $token,
                ],
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]
        );

        return json_decode($response->getBody(), true) ?? [];
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
            'nickname' => $user['username'] ?? null,
            'name' => $user['username'] ?? null,
            'email' => '',
            'avatar' => $user['portrait'] ? 'http://tb.himg.baidu.com/sys/portraitn/item/' . $user['portrait'] : null,
        ]);
    }
}
