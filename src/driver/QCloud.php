<?php

namespace isszz\third\driver;

use isszz\third\Driver;
use isszz\third\interfaces\DriverInterface;
use isszz\third\Exception\AuthorizeFailedException;
use isszz\third\User;

class QCloud extends Driver implements DriverInterface
{
    protected array $scopes = ['login'];
    protected string $accessTokenKey = 'UserAccessToken';
    protected string $refreshTokenKey = 'UserRefreshToken';
    protected string $expiresInKey = 'ExpiresAt';
    protected ?string $openId;
    protected ?string $unionId;

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlBase('https://cloud.tencent.com/open/authorize');
    }

    protected function getTokenUrl(): string
    {
        return '';
    }

    protected function getSecretKey(): string
    {
        return $this->config['secret_key'];
    }

    /**
     * @param  string  $code
     *
     * @return array
     * @throws \isszz\third\Exception\AuthorizeFailedException
     */
    public function getAccessToken(string $code): array
    {
        $response = $this->performRequest(
            'GET',
            'open.tencentcloudapi.com',
            'GetUserAccessToken',
            '2018-12-25',
            [
                'query' => [
                    'UserAuthCode' => $code,
                ],
            ]
        );

        return $this->parseAccessToken($response);
    }

    /**
     * @param string $token
     *
     * @return array
     * @throws \isszz\third\Exception\AuthorizeFailedException
     */
    protected function getUserByToken(string $token): array
    {
        $secret = $this->getFederationToken($token);

        return $this->performRequest(
            'GET',
            'open.tencentcloudapi.com',
            'GetUserBaseInfo',
            '2018-12-25',
            [
                'headers' => [
                    'X-TC-Token' => $secret['Token'],
                ],
            ],
            $secret['TmpSecretId'],
            $secret['TmpSecretKey'],
        );
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
                'id' => $this->openId ?? null,
                'name' => $user['Nickname'] ?? null,
                'nickname' => $user['Nickname'] ?? null,
            ]
        );
    }

    public function performRequest(string $method, string $host, string $action, string $version, array $options = [], ?string $secretId = null, ?string $secretKey = null)
    {
        $method = \strtoupper($method);
        $timestamp = \time();
        $credential = \sprintf('%s/%s/tc3_request', \gmdate('Y-m-d', $timestamp), $this->getServiceFromHost($host));
        $options['headers'] = \array_merge(
            $options['headers'] ?? [],
            [
                'X-TC-Action' => $action,
                'X-TC-Timestamp' => $timestamp,
                'X-TC-Version' => $version,
                'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8',
            ]
        );

        $signature = $this->sign($method, $host, $options['query'] ?? [], '', $options['headers'], $credential, $secretKey);
        $options['headers']['Authorization'] =
            \sprintf(
                'TC3-HMAC-SHA256 Credential=%s/%s, SignedHeaders=content-type;host, Signature=%s',
                $secretId ?? $this->getSecret(),
                $credential,
                $signature
            );
        $options['debug'] = \fopen(runtime_path('logs/third-'. \cfyun\Helper::time2str(\cfyun\Helper::time(), 'Y-m-d').'.log'), 'w+');
        $response = $this->getHttpClient()->get("https://{$host}/", $options);

        $response = json_decode($response->getBody()->getContents(), true) ?? [];

        if (!empty($response['Response']['Error'])) {
            throw new AuthorizeFailedException(
                \sprintf('%s: %s', $response['Response']['Error']['Code'], $response['Response']['Error']['Message']),
                $response
            );
        }

        return $response['Response'] ?? [];
    }

    protected function sign(string $requestMethod, string $host, array $query, string $payload, $headers, $credential, ?string $secretKey = null)
    {
        $canonicalRequestString = \join(
            "\n",
            [
                $requestMethod,
                '/',
                \http_build_query($query),
                "content-type:{$headers['Content-Type']}\nhost:{$host}\n",
                "content-type;host",
                hash('SHA256', $payload),
            ]
        );

        $signString = \join(
            "\n",
            [
                'TC3-HMAC-SHA256',
                $headers['X-TC-Timestamp'],
                $credential,
                hash('SHA256', $canonicalRequestString),
            ]
        );

        $secretKey = $secretKey ?? $this->getSecretKey();
        $secretDate = hash_hmac('SHA256', \gmdate('Y-m-d', $headers['X-TC-Timestamp']), "TC3{$secretKey}", true);
        $secretService = hash_hmac('SHA256', $this->getServiceFromHost($host), $secretDate, true);
        $secretSigning = hash_hmac('SHA256', "tc3_request", $secretService, true);

        return hash_hmac('SHA256', $signString, $secretSigning);
    }

    /**
     * @param string|array $body
     *
     * @return array
     * @throws \isszz\third\Exception\AuthorizeFailedException
     */
    protected function parseAccessToken($body)
    {
        if (!is_array($body)) {
            $body = json_decode($body, true);
        }

        if (empty($body['UserOpenId'])) {
            throw new AuthorizeFailedException('Authorize Failed: ' . json_encode($body, JSON_UNESCAPED_UNICODE), $body);
        }

        $this->openId = $body['UserOpenId'] ?? null;
        $this->unionId = $body['UserUnionId'] ?? null;

        return $body;
    }

    /**
     * @param string $accessToken
     *
     * @return mixed
     * @throws \isszz\third\Exception\AuthorizeFailedException
     */
    protected function getFederationToken(string $accessToken)
    {
        $response = $this->performRequest(
            'GET',
            'sts.tencentcloudapi.com',
            'GetThirdPartyFederationToken',
            '2018-08-13',
            [
                'query' => [
                    'UserAccessToken' => $accessToken,
                    'Duration' => 7200,
                    'ApiAppId' => 0,
                ],
                'headers' => [
                    'X-TC-Region' => 'ap-guangzhou',
                ]
            ]
        );

        if (empty($response['Credentials'])) {
            throw new AuthorizeFailedException('Get Federation Token failed.', $response);
        }

        return $response['Credentials'];
    }

    protected function getCodeFields(): array
    {
        $fields = array_merge(
            [
                'app_id' => $this->getAppId(),
                'redirect_url' => $this->redirectUrl,
                'scope' => $this->formatScopes($this->scopes, $this->scopeSeparator),
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
     * @param string $host
     *
     * @return mixed|string
     */
    protected function getServiceFromHost(string $host)
    {
        return explode('.', $host)[0];
    }

    /**
     * 组装配置表单参数
     *
     * @param array $config
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
                'name' => 'SecretId',
                'tips' => 'AppKey或SecretId',
                'placeholder' => '请输入SecretId',
            ],
            'secret_key' => [
                'type' => 'input',
                'name' => 'SecretKey',
                'tips' => '',
                'placeholder' => '请输入SecretKey',
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
}
