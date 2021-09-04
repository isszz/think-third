<?php

namespace isszz\third\driver;

use isszz\third\User;
use isszz\third\Driver;
use isszz\third\interfaces\DriverInterface;
use isszz\third\exception\InvalidArgumentException;

use Psr\Http\Message\ResponseInterface;

class Wechat extends Driver implements DriverInterface
{
    /**
     * The base url of WeChat API.
     *
     * @var string
     */
    protected string $baseUrl = 'https://api.weixin.qq.com/sns';

    /**
     * User openid.
     *
     * @var string
     */
    protected string $openId;

    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected array $scopes = ['snsapi_login'];

    /**
     * Return country code instead of country name.
     *
     * @var bool
     */
    protected bool $withCountryCode = false;

    /**
     * @var array
     */
    protected ?array $component = null;

    /**
     * Set user openid.
     * 
     * @param string $openid
     *
     * @return $this
     */
    public function withOpenid(string $openid): self
    {
        $this->openid = $openid;

        return $this;
    }

    /**
     * Return country code instead of country name.
     *
     * @return $this
     */
    public function withCountryCode()
    {
        $this->withCountryCode = true;
        return $this;
    }

    /**
     * WeChat OpenPlatform 3rd component.
     *
     * @param  array  $componentConfig  ['id' => xxx, 'token' => xxx]
     *
     * @return \isszz\third\driver\WeChat
     * @throws \isszz\third\Exceptions\InvalidArgumentException
     */
    public function withComponent(array $componentConfig)
    {
        $this->prepareForComponent($componentConfig);

        return $this;
    }

    public function getComponent()
    {
        return $this->component;
    }

    /**
     * Get the authentication URL for the provider.
     *
     * @return string
     */
    protected function getAuthUrl(): string
    {
        $path = 'oauth2/authorize';

        if (in_array('snsapi_login', $this->scopes)) {
            $path = 'qrconnect';
        }

        return $this->buildAuthUrlBase("https://open.weixin.qq.com/connect/{$path}");
    }

    protected function buildAuthUrlBase(string $url): string
    {
        $query = http_build_query($this->getCodeFields(), '', '&', $this->encodingType);

        return $url . '?' . $query . '#wechat_redirect';
    }

    protected function getCodeFields(): array
    {
        if (!empty($this->component)) {
            $this->with(array_merge($this->parameters, ['component_appid' => $this->component['id']]));
        }

        return array_merge([
            'appid' => $this->getAppId(),
            'redirect_uri' => $this->redirectUrl,
            'response_type' => 'code',
            'scope' => $this->formatScopes($this->scopes, $this->scopeSeparator),
            'state' => $this->state ?: md5(uniqid()),
            'connect_redirect' => 1,
        ], $this->parameters);
    }

    protected function getTokenUrl(): string
    {
        if ($this->component) {
            return $this->baseUrl.'/oauth2/component/access_token';
        }

        return $this->baseUrl.'/oauth2/access_token';
    }
    
    /**
     * @param string $code
     *
     * @return \isszz\third\User
     * @throws \isszz\third\Exceptions\AuthorizeFailedException | \GuzzleHttp\Exception\GuzzleException
     */
    public function user(?string $code = null): User
    {
        if (in_array('snsapi_base', $this->scopes)) {
            return $this->mapUserToObject(\json_decode($this->getTokenFromCode($code)->getBody()->getContents(), true) ?? []);
        }

        $token = $this->getAccessToken($code);

        $this->withOpenid($token['openid']);

        $user = $this->buildUserByToken($token[$this->accessTokenKey]);

        return $user->setRefreshToken($token['refresh_token'])
            ->setExpiresIn($token['expires_in']);
    }

    protected function getUserByToken(string $token): array
    {
        $language = $this->withCountryCode ? null : (isset($this->parameters['lang']) ? $this->parameters['lang'] : 'zh_CN');

        $response = $this->getHttpClient()->get($this->baseUrl . '/userinfo', [
            'query' => array_filter([
                'access_token' => $token,
                'openid' => $this->openid,
                'lang' => $language,
            ]),
        ]);

        return \json_decode($response->getBody()->getContents(), true) ?? [];
    }

    protected function mapUserToObject(array $user): User
    {
        return new User([
            'id' => $user['openid'] ?? null,
            'name' => $user['nickname'] ?? null,
            'nickname' => $user['nickname'] ?? null,
            'avatar' => $user['headimgurl'] ?? null,
            'email' => null,
        ]);
    }

    /**
     * @param string $code
     *
     * @return array
     */
    protected function getTokenFields(string $code): array
    {
        if (!empty($this->component)) {
            return [
                'appid' => $this->getAppId(),
                'component_appid' => $this->component['id'],
                'component_access_token' => $this->component['token'],
                'code' => $code,
                'grant_type' => 'authorization_code',
            ];
        }

        return [
            'appid' => $this->getAppId(),
            'secret' => $this->getSecret(),
            'code' => $code,
            'grant_type' => 'authorization_code',
        ];
    }

    /**
     * @param  string  $code
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function getTokenFromCode(string $code): ResponseInterface
    {
        return $this->getHttpClient()->get($this->getTokenUrl(), [
            'headers' => ['Accept' => 'application/json'],
            'query' => $this->getTokenFields($code),
        ]);
    }

    protected function prepareForComponent(array $component)
    {
        $config = [];
        foreach ($component as $key => $value) {
            if (\is_callable($value)) {
                $value = \call_user_func($value, $this);
            }

            switch ($key) {
                case 'id':
                case 'oappid':
                case 'app_id':
                case 'component_app_id':
                    $config['id'] = $value;
                    break;
                case 'token':
                case 'osecret':
                case 'app_token':
                case 'access_token':
                case 'component_access_token':
                    $config['token'] = $value;
                    break;
            }
        }

        if (2 !== count($config)) {
            throw new InvalidArgumentException('Please check your config arguments is available.');
        }

        if (1 === count($this->scopes) && in_array('snsapi_login', $this->scopes)) {
            $this->scopes = ['snsapi_base'];
        }

        $this->component = $config;
    }

    /**
     * Build form data
     *
     * @param array $config
     * 
     * @return array
     */
    public function formData(array $config = []): array
    {
        $data = [
            'open' => [
                'type' => 'switch',
                'name' => '是否启用',
                'tips' => '',
                'placeholder' => '',
            ],
            'name' => [
                'type' => 'input',
                'name' => '第三方名称',
                'tips' => '',
                'placeholder' => '请输入第三方名称',
            ],
            'icon' => [
                'type' => 'input',
                'name' => '图标',
                'tips' => '字体图标Class名',
                'placeholder' => '请输入字体图标Class名',
            ],
            'svg' => [
                'type' => 'textarea',
                'name' => 'SVG图标',
                'tips' => '如果没有字体图标可以用此替代',
                'placeholder' => 'SVG图标代码',
            ],
            'appid' => [
                'type' => 'input',
                'name' => 'AppId',
                'tips' => 'appid或app_key',
                'placeholder' => '请输入AppId',
            ],
            'secret' => [
                'type' => 'input',
                'name' => 'AppKey',
                'tips' => 'AppKey或app_secret',
                'placeholder' => '请输入AppKey',
            ],
            'scope' => [
                'type' => 'textarea',
                'name' => '权限接口',
                'tips' => '多个权限接口请用空格分割',
                'placeholder' => '请输入权限接口，可留空',
            ],
            'oappid' => [
                'type' => 'input',
                'name' => '开放平台AppId',
                'tips' => '开放平台Appid或app_key，用于扫码登录',
                'placeholder' => '请输入开放平台AppId',
            ],
            'osecret' => [
                'type' => 'input',
                'name' => '开放平台AppKey',
                'tips' => '开放平台AppKey或app_secret，用于扫码登录',
                'placeholder' => '请输入开放平台AppKey',
            ],
            'orderid' => [
                'type' => 'number',
                'name' => '排序',
                'tips' => '数字越小越靠前',
                'placeholder' => '数字越小越靠前，可留空',
            ],
        ];

        if(empty($config)) {
            return $data;
        }

        return $this->setFormData($data, $config);
    }
}
