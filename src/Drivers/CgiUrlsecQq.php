<?php

namespace Weijiajia\TencentUrlDetection\Drivers;

use Saloon\Enums\Method;
use Weijiajia\TencentUrlDetection\Request;
use Weijiajia\TencentUrlDetection\Response;

class CgiUrlsecQq extends Request
{
    protected string $url;

    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return 'https://cgi.urlsec.qq.com/index.php';
    }

    public function defaultQuery(): array
    {
        return [
            'url' => $this->url,
            'm' => 'url',
            'a' => 'validUrl',
        ];
    }

    public function defaultHeaders(): array
    {
        return [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Encoding' => 'gzip, deflate, br, zstd',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Connection' => 'keep-alive',
            'Host' => 'cgi.urlsec.qq.com',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Sec-Fetch-User' => '?1',
            'Upgrade-Insecure-Requests' => '1',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36',
            'sec-ch-ua' => '"Chromium";v="136", "Google Chrome";v="136", "Not.A/Brand";v="99"',
            'sec-ch-ua-mobile' => '?0',
        ];
    }

    public function check(string $url): Response
    {
        $this->url = $url;
        $response = $this->send();

        return new Response($url, 'ok' === $response->json('data'), $response);
    }
}
