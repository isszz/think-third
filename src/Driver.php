<?php

namespace isszz\third;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\ClientInterface;

use think\Request;
use think\Session;
use think\helper\Str;

use isszz\third\AccessToken;
use isszz\third\interfaces\DriverInterface;
use isszz\third\interfaces\AccessTokenInterface;
use isszz\third\exception\AuthorizeFailedException;
use isszz\third\exception\InvalidStateException;
use isszz\third\exception\InvalidArgumentException;

abstract class Driver implements DriverInterface
{
    /**
     * The app session.
     */
    protected Session $session;

    /**
     * The HTTP request instance.
     */
    protected Request $request;
    
    /**
     * The third config.
     */
    protected array $config = [];

    /**
     * state
     *
     * @var string
     */
    protected ?string $state = null;

    /**
     * The redirect URL.
     *
     * @var string
     */
    protected ?string $redirectUrl;

    /**
     * The custom parameters to be sent with the request.
     *
     * @var array
     */
    protected array $parameters = [];

    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected array $scopes = [];

    /**
     * The separating character for the requested scopes.
     *
     * @var string
     */
    protected string $scopeSeparator = ' ';

    /**
     * The type of the encoding in the query.
     *
     * @var int Can be either PHP_QUERY_RFC3986 or PHP_QUERY_RFC1738
     */
    protected int $encodingType = PHP_QUERY_RFC1738;

    protected string $expiresInKey    = 'expires_in';
    protected string $accessTokenKey  = 'access_token';
    protected string $refreshTokenKey = 'refresh_token';

    /**
     * GuzzleHttp\Client
     */
    protected Client $httpClient;

    /**
     * The config for GuzzleHttp\client.
     *
     * @var array
     */
    protected array $guzzleOptions   = []; // ['http_errors' => false];


    public function __construct(Request $request, Session $session, $config)
    {
        if (empty($config['appid']) || empty($config['secret'])) {
            throw new InvalidArgumentException('Appid and secret config cannot be empty.');
        }

        $this->config = $config;
        $this->request = $request;
        $this->session = $session;

        // set scopes
        if(!empty($config['scopes'])) {
            if(is_array($config['scopes'])) {
                $this->scopes = $config['scopes'];
            } elseif(is_string($config['scopes'])) {
                $this->scopes = array($config['scopes']);
            }
        }
        
        // set redirect_url
        if (empty($config['redirect_url'])) {
            $config['redirect_url'] = $config['redirect'] ?? '';
            // $config['redirect_url'] = empty($config['redirect']) ? '' : $config['redirect'] . '/' . $this->getName();
        }

        $this->redirectUrl = $config['redirect_url'];
    }

    /**
     * Get the authentication URL for the provider.
     *
     * @param string $state
     *
     * @return string
     */
    abstract protected function getAuthUrl(): string;

    /**
     * Get the token URL for the provider.
     *
     * @return string
     */
    abstract protected function getTokenUrl(): string;

    /**
     * Get the raw user for the given access token.
     *
     * @param string $token
     *
     * @return array
     */
    abstract protected function getUserByToken(string $token): array;

    /**
     * Map the raw user array to a Socialite User instance.
     *
     * @param array $user
     *
     * @return \isszz\third\User
     */
    abstract protected function mapUserToObject(array $user): User;

    /**
     * Redirect to authorize url
     */
    public function redirect(?string $redirectUrl = null)
    {
        if (!empty($redirectUrl)) {
            $this->setRedirectUrl($redirectUrl);
        }

        // dd(urldecode($this->getAuthUrl()));
        return redirect($this->getAuthUrl());
    }

    /**
     * Set redirect url.
     *
     * @param string $redirectUrl
     *
     * @return $this
     */
    public function setRedirectUrl(string $redirectUrl): self
    {
        $this->redirectUrl = $redirectUrl;

        return $this;
    }

    /**
     * Return the redirect url.
     *
     * @return string
     */
    public function getRedirectUrl()
    {
        return $this->redirectUrl;
    }
    
    /**
     * Get the request instance.
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Code callback
     * 
     * @param string $url
     * @param mixed $code
     * 
     * Redirect to get access_token url
     */
    public function callback(string $url, ?string $code = null)
    {
        if (is_null($code)) {
            $code = $this->getCode();
        }
        
        if(empty($code)) {
            throw new InvalidArgumentException('Code parameter cannot be empty.');
        }

        $tokenResponse = $this->getAccessToken($code);
        
        $params = [
            'token' => $tokenResponse[$this->accessTokenKey],
            'refresh_token' => $tokenResponse[$this->refreshTokenKey] ?? null,
            'expires_in' => $tokenResponse[$this->expiresInKey] ?? null,
            'type' => $tokenResponse['token_type'] ?? 'bearer',
        ];

        $url = $url . '?' . \http_build_query($params, '', '&', $this->encodingType);

        return redirect($url);
    }

    /**
     * Get user for code
     * 
     * @return \isszz\third\User
     */
    public function user(?string $code = null): User
    {
        if (is_null($code)) {
            $code = $this->getCode();
        }
        
        if(empty($code)) {
            throw new InvalidArgumentException('Code parameter cannot be empty.');
        }

        $tokenResponse = $this->getAccessToken($code);

        $user = $this->buildUserByToken($tokenResponse[$this->accessTokenKey]);

        return $user->setRefreshToken($tokenResponse[$this->refreshTokenKey] ?? null)
            ->setExpiresIn($tokenResponse[$this->expiresInKey] ?? null)
            ->setTokenResponse($tokenResponse);
    }

    /**
     * Get user for Token
     * 
     * @return \isszz\third\User
     */
    public function userForToken(?string $token = null): User
    {
        if (is_null($token)) {
            $token = $this->request->param('token');
        }
        
        if(empty($token)) {
            throw new InvalidArgumentException('Token parameter cannot be empty.');
        }

        $user = $this->buildUserByToken($token);

        return $user->setRefreshToken($this->request->param('refresh_token') ?? null)
            ->setExpiresIn($this->request->param('expires_in') ?? null);
    }

    /**
     * Get user for access_token
     * 
     * @return \isszz\third\User
     */
    public function buildUserByToken(string $token): User
    {
        $user = $this->getUserByToken($token);

        return $this->mapUserToObject($user)->setDriver($this)->setRaw($user)->setToken($token);
    }

    /**
     * Get access_token for code
     * 
     * @param string $code
     * 
     * @return array
     * @throws \isszz\third\Exceptions\AuthorizeFailedException | \GuzzleHttp\Exception\GuzzleException
     */
    public function getAccessToken(string $code): array
    {
        $response = $this->getHttpClient()->post(
            $this->getTokenUrl(),
            [
                'form_params' => $this->getTokenFields($code),
                'headers'     => [
                    'Accept' => 'application/json',
                ],
            ]
        );
        return $this->normalizeAccessTokenResponse($response->getBody()->getContents());
    }

    /**
     * @param string $state
     *
     * @return $this
     */
    public function withState(string $state): self
    {
        $this->state = $state;

        return $this;
    }

    /**
     * Set the scopes of the requested access.
     *
     * @param array $scopes
     *
     * @return $this
     */
    public function scopes(array $scopes)
    {
        $this->scopes = $scopes;

        return $this;
    }

    /**
     * Set the custom parameters of the request.
     *
     * @param array $parameters
     *
     * @return $this
     */
    public function with(array $parameters)
    {
        $this->parameters = $parameters;

        return $this;
    }

    public function getAppId(): ?string
    {
        return $this->config['appid'];
    }

    public function getSecret(): ?string
    {
        return $this->config['secret'];
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @throws \ReflectionException
     *
     * @return string
     */
    public function getName()
    {
        if (empty($this->name)) {
            $this->name = (new \ReflectionClass(get_class($this)))->getShortName();
        }

        return \strtolower($this->name);
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
            'item2' => [
                'type' => 'tbody',
                'class' => 'J_tbody_1',
                'items' => [
                    'open' => [
                        'type' => 'switch-list',
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
                ],
            ],
            'checkitem' => [
                'type' => 'checkitem',
                'name' => 'CheckItem',
                'tips' => 'CheckItem',
                'placeholder' => '',
                'items' => [
                    'aa' => '未取得未群',
                    'bb' => '他已经同意',
                    'cc' => '风格不放过',
                    'dd' => '五色风',
                ],
            ],
            'switch' => [
                'type' => 'switch',
                'name' => 'switch',
                'tips' => 'switch',
                'class' => 'icon',
                'switch' => '.J_tbody_1',
            ],
            'open' => [
                'type' => 'switch-list',
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

    /**
     * Format the given scopes.
     *
     * @param array  $scopes
     * @param string $scopeSeparator
     *
     * @return string
     */
    protected function formatScopes(array $scopes, $scopeSeparator)
    {
        return implode($scopeSeparator, $scopes);
    }

    /**
     * Get the POST fields for the token request.
     *
     * @param string $code
     *
     * @return array
     */
    protected function getTokenFields(string $code): array
    {
        return [
            'client_id' => $this->getAppId(),
            'client_secret' =>  $this->getSecret(),
            'code' => $code,
            'redirect_uri' => $this->redirectUrl,
        ];
    }

    /**
     * Get the authentication URL for the provider.
     *
     * @param string $url
     *
     * @return string
     */
    protected function buildAuthUrlBase(string $url): string
    {
        $query = $this->getCodeFields() + ($this->state ? ['state' => $this->state] : []);

        return $url . '?' . \http_build_query($query, '', '&', $this->encodingType);
    }

    /**
     * Get the GET parameters for the code request.
     *
     * @param string|null $state
     *
     * @return array
     */
    protected function getCodeFields(): array
    {
        $fields = array_merge(
            [
                'client_id'     => $this->getAppId(),
                'redirect_uri'  => $this->redirectUrl,
                'scope'         => $this->formatScopes($this->scopes, $this->scopeSeparator),
                'response_type' => 'code',
            ],
            $this->parameters
        );

        if ($this->state) {
            $fields['state'] = $this->state;
        }

        return $fields;
    }

    /**
     * Get the code from the request.
     *
     * @return string
     */
    protected function getCode()
    {
        return $this->request->param('code', $this->request->param('auth_code'));
    }

    /**
     * Get Guzzle HTTP client.
     * 
     * @return \GuzzleHttp\Client
     */
    public function getHttpClient(): Client
    {
        return $this->httpClient ?? new Client($this->guzzleOptions);
    }

    /**
     * @param array $config
     *
     * @return self
     */
    public function setGuzzleOptions($config = []): self
    {
        $this->guzzleOptions = $config;

        return $this;
    }

    public function getGuzzleOptions(): array
    {
        return $this->guzzleOptions;
    }

    /**
     * @param array|string $response
     *
     * @return mixed
     * @return array
     * @throws \isszz\third\exception\AuthorizeFailedException
     *
     */
    protected function normalizeAccessTokenResponse($response): array
    {
        if ($response instanceof Stream) {
            $response->rewind();
            $response = $response->getContents();
        }


        if (\is_string($response)) {
            $response = json_decode($response, true) ?? [];
        }

        if (!\is_array($response)) {
            throw new AuthorizeFailedException('Invalid token response', [$response]);
        }

        if (empty($response[$this->accessTokenKey])) {
            throw new AuthorizeFailedException('Authorize Failed: ' . json_encode($response, JSON_UNESCAPED_UNICODE), $response);
        }

        return $response + [
                'access_token'  => $response[$this->accessTokenKey],
                'refresh_token' => $response[$this->refreshTokenKey] ?? null,
                'expires_in'    => \intval($response[$this->expiresInKey] ?? 0),
            ];
    }

	protected function setFormData(array $data, array $config): array
	{
        $list = [];
		foreach ($data as $k => $v) {
            if($v['type'] == 'tbody') {

                foreach ($v['items'] as $_k => $_v) {
                    $defaultValue = isset($config[$k][$_k]) ? $config[$k][$_k] : '';
                    $v['items'][$_k] = ['config' => $data[$_k], 'value' => $defaultValue];
                }

                $list[$k] = $v;
                
                continue;
            }

            $defaultValue = isset($config[$k]) ? $config[$k] : '';

            $list[$k] = ['config' => $data[$k], 'value' => $defaultValue];

		}

        // dd($list);
		return $list;
	}
}
