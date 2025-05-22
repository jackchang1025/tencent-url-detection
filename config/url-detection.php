<?php

return [
    'default' => env('URL_DETECTION_DRIVER', 'cgi_urlsec_qq'),

    'drivers' => [
        'cgi_urlsec_qq' => [
            // 腾讯公共API驱动不需要额外配置
        ],
        'rrbay' => [
            'key' => env('RRBAY_API_KEY'),
        ],
        'two_captcha' => [
            'api_key' => env('TWOCAPTCHA_API_KEY'),
            'appid' => env('TENCENT_CAPTCHA_APPID', '2046626881'), // 腾讯验证码 appid
        ],
    ],
];
