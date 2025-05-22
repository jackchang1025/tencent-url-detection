<?php

namespace Tests\Feature\Drivers;

use Saloon\Exceptions\Request\RequestException; // 继承 Laravel 的 TestCase
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\TestCase;
use Weijiajia\TencentUrlDetection\Connectors\TencentUrlsecQqConnector;
use Weijiajia\TencentUrlDetection\Contracts\Driver; // 引入 Connector
use Weijiajia\TencentUrlDetection\Drivers\CgiUrlsecQq;
use Weijiajia\TencentUrlDetection\Response as TencentResponse;

// Alias the Response class

// Test the CgiUrlsecQq driver can check a safe URL.
test('cgi urlsec qq driver can check safe url', function () {
    // 1. 创建一个 MockClient，用于模拟 Saloon 请求
    $mockClient = new MockClient([
        // 为特定的请求 Endpoint 配置模拟响应
        MockResponse::make([
            'data' => 'ok', // 模拟腾讯API返回的"安全"响应
            'reCode' => 0,
        ], 200),
    ]);

    // 2. 将 MockClient 挂载到 TencentUrlsecQqConnector 上
    //    当通过这个 Connector 发送请求时，Saloon 会使用 MockClient 的模拟响应
    $driver = new CgiUrlsecQq();
    $driver->withMockClient($mockClient);

    // 4. 调用驱动的 check 方法进行测试
    $urlToCheck = 'https://example.com';
    $response = $driver->check($urlToCheck);

    $mockClient->assertSent(function ($request, $response) use ($urlToCheck) {
        return $request->query()->get('url') === $urlToCheck
            && 'url' === $request->query()->get('m')
            && 'validUrl' === $request->query()->get('a');
    });

    // 5. 编写断言，验证结果是否符合预期
    expect($response)->toBeInstanceOf(TencentResponse::class);
    expect($response->getUrl())->toBe($urlToCheck);
    expect($response->isWeChatRiskWarning())->toBeTrue(); // 验证是否被标记为风险
    expect($response->getWordingTitle())->toBe(''); // 验证其他字段是否为默认值
    expect($response->getWording())->toBe('');
});

// Test the CgiUrlsecQq driver can check a risky URL.
test('cgi urlsec qq driver can check risky url', function () {
    // 1. 创建一个 MockClient，模拟风险响应
    $mockClient = new MockClient([
        MockResponse::make([
            'data' => 'risk', // 假设腾讯API返回"风险"标识
            'reCode' => 0,
            // 根据实际API返回的风险结构模拟更多数据，例如 wordings
            'results' => [
                'url' => 'risky.com',
                'whitetype' => 1, // 假设 whitetype=1 表示风险
                'WordingTitle' => '风险提示',
                'Wording' => '这是一个风险网站',
            ],
        ], 200),
    ]);

    // 2. 将 MockClient 挂载到 Connector
    $driver = new CgiUrlsecQq();
    $driver->withMockClient($mockClient);

    // 4. 调用 check 方法
    $urlToCheck = 'https://risky.com';
    $response = $driver->check($urlToCheck);

    // 5. 编写断言
    expect($response)->toBeInstanceOf(TencentResponse::class);
    expect($response->getUrl())->toBe($urlToCheck);
    // 根据驱动中解析风险的逻辑来断言 IsWeChatRiskWarning
    // 如果是根据 whitetype=2 判断安全，那么 whitetype=1 应该是不安全
    expect($response->isWeChatRiskWarning())->toBeFalse();
    expect($response->getWordingTitle())->toBe('');
    expect($response->getWording())->toBe('');
});

// Test the CgiUrlsecQq driver throws exception on API error.
test('cgi urlsec qq driver throws exception on api error', function () {
    $mockClient = new MockClient([
        MockResponse::make('Server Error', 500), // 模拟服务器错误
    ]);

    $driver = new CgiUrlsecQq();
    $driver->withMockClient($mockClient);

    $urlToCheck = 'https://example.com';

    // 断言调用 check 方法会抛出 Saloon 的 RequestException 异常
    expect(fn () => $driver->check($urlToCheck))->toThrow(RequestException::class);
});
