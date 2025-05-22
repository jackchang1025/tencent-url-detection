<?php

namespace Weijiajia\TencentUrlDetection\Drivers;

use Saloon\Enums\Method;
use Saloon\Http\SoloRequest;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use Weijiajia\TencentUrlDetection\Contracts\Driver;
use Weijiajia\TencentUrlDetection\Response;

class Rrbay extends SoloRequest implements Driver
{
    use AlwaysThrowOnErrors;

    protected string $url;

    protected Method $method = Method::GET;

    public function __construct(
        protected string $key,
    ) {}

    public function resolveEndpoint(): string
    {
        return 'http://wx.rrbay.com/pro/wxUrlCheck2.ashx';
    }

    public function defaultQuery(): array
    {
        return [
            'key' => $this->key,
            'url' => $this->url,
        ];
    }

    public function check(string $url): Response
    {
        $this->url = $url;

        $response = $this->send();

        return new Response($url, '101' === $response->json('Code'), $response);
    }
}
