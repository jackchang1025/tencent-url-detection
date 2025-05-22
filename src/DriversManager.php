<?php

namespace Weijiajia\TencentUrlDetection;

use Illuminate\Support\Manager;
use Weijiajia\TencentUrlDetection\Drivers\CgiUrlsecQq;
use Weijiajia\TencentUrlDetection\Drivers\Rrbay;
use Weijiajia\TencentUrlDetection\Drivers\TwoCaptcha;

class DriversManager extends Manager
{
    public function getDefaultDriver(): ?string
    {
        return $this->config->get('tencent-url-detection.default');
    }

    public function driver($driver = null): Request
    {
        return parent::driver($driver);
    }

    /**
     * 创建代理服务实例.
     */
    public function connector(?string $driver = null): Request
    {
        return $this->driver($driver);
    }

    public function createCgiUrlsecQqDriver(): CgiUrlsecQq
    {
        return new CgiUrlsecQq();
    }

    public function createTwoCaptchaDriver(): TwoCaptcha
    {
        return new TwoCaptcha(
            new \TwoCaptcha\TwoCaptcha($this->config->get('tencent-url-detection.drivers.two_captcha.api_key')),
            $this->config->get('tencent-url-detection.drivers.two_captcha.appid')
        );
    }

    public function createRrbayDriver(): Rrbay
    {
        return new Rrbay($this->config->get('tencent-url-detection.drivers.rrbay.key'));
    }
}
