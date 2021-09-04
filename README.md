# Thinkphp 中使用 第三方登录

# 安装

```shell
composer require isszz/think-third
```

# 配置

在 config/hashids.php 中更改

```php
<?php

declare(strict_types=1);

return [
    // 默认连接名称
    'default' => 'main',

    // Hashids Connections
    'connections' => [
        'main' => [
            'salt' => 'salt-string',
            'length' => 'length-integer',
        ],
        'other' => [
            'salt' => 'salt-string',
            'length' => 'length-integer',
        ],
    ],
];
```

## 基础用法 qiniu, oss, cos 并无差别

```php

```

- 查看更多用法: [vinkla/hashids](https://github.com/vinkla/hashids)