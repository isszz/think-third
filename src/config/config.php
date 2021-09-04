<?php

return [
    'apps' => [
        'qq' => [
            'appid' => '',
            'secret' => ''
        ],
        'weichat' => [
            'appid' => '',
            'secret' => ''
        ],
        'github' => [
            'appid' => '',
            'secret' => ''
        ]
    ],
    'controller' => \isszz\thrid\Controller::class,
    'user_checker' => null,
    'redirect' => [
        'bind' => '/',
        'register' => '/',
        'complete' => '/'
    ]
];