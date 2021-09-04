<?php

namespace isszz\third\driver;

use isszz\third\Driver;
use isszz\third\interfaces\DriverInterface;
use isszz\third\Exception\InvalidArgumentException;
use isszz\third\User;

/**
 * @see https://open.taobao.com/doc.htm?docId=102635&docType=1&source=search [Taobao - OAuth 2.0 授权登录]
 */
class Alipay extends Driver implements DriverInterface
{
    protected string $baseUrl = 'https://openapi.alipay.com/gateway.do';
    // protected string $baseUrl = 'https://openapi.alipaydev.com/gateway.do';
    
    protected array $scopes = ['auth_user'];
    protected string $apiVersion = '1.0';
    protected string $signType = 'RSA2';
    protected string $postCharset = 'UTF-8';
    protected string $format = 'json';

    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlBase('https://openauth.alipay.com/oauth2/publicAppAuthorize.htm');
        // return $this->buildAuthUrlBase('https://openauth.alipaydev.com/oauth2/publicAppAuthorize.htm');
    }

    protected function getTokenUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @param  string  $token
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \isszz\third\Exception\AuthorizeFailedException
     */
    protected function getUserByToken(string $token): array
    {
        $params = $this->getPublicFields('alipay.user.info.share');
        $params += ['auth_token' => $token];
        $params['sign'] = $this->generateSign($params);

        $response = $this->getHttpClient()->post(
            $this->baseUrl,
            [
                'form_params' => $params,
                'headers' => [
                    "content-type" => "application/x-www-form-urlencoded;charset=utf-8",
                ],
            ]
        );

        $response = json_decode($response->getBody()->getContents(), true);

        if (!empty($response['error_response']) || empty($response['alipay_user_info_share_response'])) {
            throw new \InvalidArgumentException('You have error! ' . \json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        return $response['alipay_user_info_share_response'];
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
                'id' => $user['user_id'] ?? null,
                'name' => $user['nick_name'] ?? null,
                'avatar' => $user['avatar'] ?? null,
                'email' => $user['email'] ?? null,
            ]
        );
    }

    /**
     * @param  string  $code
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \isszz\third\Exception\AuthorizeFailedException
     * @throws \isszz\third\Exception\InvalidArgumentException
     */
    public function getAccessToken(string $code): array
    {
        $response = $this->getHttpClient()->post(
            $this->getTokenUrl(),
            [
                'form_params' => $this->getTokenFields($code),
                'headers' => [
                    "content-type" => "application/x-www-form-urlencoded;charset=utf-8",
                ],
            ]
        );
        $response = json_decode($response->getBody()->getContents(), true);

        if (!empty($response['error_response'])) {
            throw new \InvalidArgumentException('You have error! ' . json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        return $this->normalizeAccessTokenResponse($response['alipay_system_oauth_token_response']);
    }

    /**
     * @return array
     * @throws \isszz\third\Exception\InvalidArgumentException
     */
    protected function getCodeFields(): array
    {
        if (empty($this->redirectUrl)) {
            throw new InvalidArgumentException('Please set same redirect URL like your Alipay Official Admin');
        }

        $fields = array_merge(
            [
                'app_id' => $this->getAppId(),
                'scope' => implode(',', $this->scopes),
                'redirect_uri' => $this->redirectUrl,
            ],
            $this->parameters
        );

        return $fields;
    }

    /**
     * @param string $code
     *
     * @return array|string[]
     * @throws \isszz\third\Exception\InvalidArgumentException
     */
    protected function getTokenFields(string $code): array
    {
        $params = $this->getPublicFields('alipay.system.oauth.token');
        $params += [
            'code' => $code,
            'grant_type' => 'authorization_code',
        ];
        $params['sign'] = $this->generateSign($params);

        return $params;
    }

    /**
     * @param string $method
     *
     * @return array
     */
    public function getPublicFields(string $method): array
    {
        return [
            'app_id' => $this->getAppId(),
            'format' => $this->format,
            'charset' => $this->postCharset,
            'sign_type' => $this->signType,
            'method' => $method,
            'timestamp' => date('Y-m-d H:m:s'),
            'version' => $this->apiVersion,
        ];
    }

    /**
     * @param $params
     *
     * @return string
     *
     * @see https://opendocs.alipay.com/open/289/105656
     */
    protected function generateSign($params)
    {
        ksort($params);

        $signContent = $this->buildParams($params);
        $key =  $this->getSecret();
        $signValue = $this->signWithSHA256RSA($signContent, $key);

        return $signValue;
    }

    /**
     * @param string $signContent
     * @param string $key
     *
     * @return string
     * @throws \isszz\third\Exception\InvalidArgumentException
     */
    protected function signWithSHA256RSA(string $signContent, string $key)
    {
        if (empty($key)) {
            throw new InvalidArgumentException('no RSA private key set.');
        }

        $key = "-----BEGIN RSA PRIVATE KEY-----\n" .
            chunk_split($key, 64, "\n") .
            "-----END RSA PRIVATE KEY-----";

        openssl_sign($signContent, $signValue, $key, OPENSSL_ALGO_SHA256);

        return base64_encode($signValue);
    }

    /**
     * @param array          $params
     * @param bool           $urlencode
     * @param array|string[] $except
     *
     * @return string
     */
    public static function buildParams(array $params, bool $urlencode = false, array $except = ['sign'])
    {
        $param_str = '';
        foreach ($params as $k => $v) {
            if (in_array($k, $except)) {
                continue;
            }
            $param_str .= $k . '=';
            $param_str .= $urlencode ? rawurlencode($v) : $v;
            $param_str .= '&';
        }

        return rtrim($param_str, '&');
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
                'name' => 'RSA私钥',
                'tips' => '支付宝rsa_private_key私钥',
                'placeholder' => '请输入RSA私钥',
            ],
            /*
            'rsa_private_key' => [
                'type' => 'input',
                'name' => 'RSA私钥',
                'tips' => '支付宝rsa_private_key私钥',
                'placeholder' => '请输入RSA私钥',
            ],*/
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
