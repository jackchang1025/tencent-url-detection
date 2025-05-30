<?php

namespace Weijiajia\TencentUrlDetection\Drivers;

use Saloon\Enums\Method;
use Weijiajia\TencentUrlDetection\Request;
use Weijiajia\TencentUrlDetection\Response;
use Weijiajia\TencentUrlDetection\TencentUrlDetectionException;

class TwoCaptcha extends Request
{
    protected string $ticket;
    protected string $randstr;
    protected string $url;

    protected Method $method = Method::GET;

    public function __construct(
        protected \TwoCaptcha\TwoCaptcha $solver,
        protected string $appid = '2046626881',
    ) {}

    public function resolveEndpoint(): string
    {
        return 'https://cgi.urlsec.qq.com/index.php';
    }

    public function defaultHeaders(): array
    {
        return [
            'accept' => '*/*',
            'accept-encoding' => 'gzip, deflate, br, zstd',
            'accept-language' => 'en-US,en;q=0.9',
            'connection' => 'keep-alive',
            'host' => 'cgi.urlsec.qq.com',
            'referer' => 'https://urlsec.qq.com/',
            'sec-ch-ua' => '"Chromium";v="136", "Google Chrome";v="136", "Not.A/Brand";v="99"',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"Windows"',
            'sec-fetch-dest' => 'script',
            'sec-fetch-mode' => 'no-cors',
            'sec-fetch-site' => 'same-site',
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36',
        ];
    }

    public function check(string $url): Response
    {
        $this->url = $url;

        $result = $this->solver->tencent([
            'sitekey' => $this->appid,
            'captcha_script' => 'https://ssl.captcha.qq.com/TCaptcha.js',
            'url' => 'https://urlsec.qq.com/check.html',
        ]);

        // 将JSON字符串解析为PHP数组或对象
        $codeData = json_decode($result->code, true); // 第二个参数true表示返回数组

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new TencentUrlDetectionException('Failed to decode JSON response: '.json_last_error_msg());
        }

        if (empty($codeData['ticket']) || empty($codeData['randstr'])) {
            throw new TencentUrlDetectionException('Invalid JSON response: missing ticket or randstr');
        }

        $this->ticket = $codeData['ticket'];
        $this->randstr = $codeData['randstr'];

        $response = $this->send();

        $rawBody = $response->body();
        $jsonString = null;

        // Example JSONP: jQueryCallback({"key":"value"})
        // We need to extract the JSON part: {"key":"value"}

        $firstParenPos = strpos($rawBody, '(');
        $lastParenPos = strrpos($rawBody, ')');

        // Validate the basic JSONP structure:
        // 1. '(' must exist and be before ')'
        // 2. ')' must exist
        // 3. ')' must be the last character of the trimmed string (to handle potential trailing whitespace)
        if (
            false !== $firstParenPos
            && false !== $lastParenPos
            && $firstParenPos < $lastParenPos
            && $lastParenPos === strlen(trim($rawBody)) - 1
        ) {
            $callbackPart = substr($rawBody, 0, $firstParenPos);

            // Validate the callback part (e.g., jQuery12345_67890)
            // It should match typical JavaScript identifier characters used for JSONP callbacks.
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $callbackPart)) {
                $jsonString = substr($rawBody, $firstParenPos + 1, $lastParenPos - $firstParenPos - 1);
            } else {
                // If the callback part doesn't match expected format, it's an invalid JSONP.
                throw new TencentUrlDetectionException(
                    'Invalid JSONP callback format in response: '.substr($rawBody, 0, $firstParenPos)
                );
            }
        } else {
            // If the response doesn't match the func({...}) structure.
            // Given the reported error, we expect JSONP. If not found, it's an issue.
            throw new TencentUrlDetectionException(
                'Response is not in the expected JSONP format (e.g., func({...})): '.substr($rawBody, 0, 100)
            );
        }

        $responseData = json_decode($jsonString, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new TencentUrlDetectionException(
                'Syntax error while decoding JSON extracted from JSONP: '.json_last_error_msg()
                    .'. Extracted content: "'.substr($jsonString, 0, 200).'"'
            );
        }
        // {
        //     "data": {
        //         "retcode": 0,
        //         "results": {
        //             "url": "fdte.cn",
        //             "whitetype": 2,
        //             "WordingTitle": "该网站含有未经证实的信息",
        //             "Wording": "该网站已被大量用户举报，含有未经证实的信息，可能会通过各种利益诱导您传播，建议您谨慎访问。",
        //             "detect_time": "1733893668",
        //             "eviltype": "2709",
        //             "certify": 0,
        //             "isDomainICPOk": 0
        //         }
        //     },
        //     "reCode": 0
        // }

        // {
        //     "data": {
        //         "retcode": 0,
        //         "results": {
        //             "url": "bzcaip.cn",
        //             "whitetype": 1,
        //             "WordingTitle": "",
        //             "Wording": "",
        //             "detect_time": "1732092934",
        //             "eviltype": "2804",
        //             "certify": 0,
        //             "isDomainICPOk": 0
        //         }
        //     },
        //     "reCode": 0
        // }

        return new Response(
            $url,
            ($responseData['data']['results']['whitetype'] ?? null) === 2, // Safely access whitetype
            $response, // Pass the original Saloon\Http\Response object
            $responseData['data']['results']['WordingTitle'] ?? '', // Default to empty string if not set
            $responseData['data']['results']['Wording'] ?? ''  // Default to empty string if not set
        );
    }

    protected function defaultQuery(): array
    {
        $timestamp = round(microtime(true) * 1000);

        // 生成随机数(模拟jQuery版本号+随机数部分)
        $random = mt_rand(1000000000000000, 9999999999999999);

        $callback = 'jQuery'.$random.'_'.$timestamp;

        $underscoreParam = $timestamp + 1;

        return [
            'm' => 'check',
            'a' => 'gw_check',
            'callback' => $callback,
            'url' => $this->url,
            'ticket' => $this->ticket,  // 正确的获取方式
            'randstr' => $this->randstr, // 正确的获取方式
            '_' => $underscoreParam,
        ];
    }
}
