<?php

use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Weijiajia\TencentUrlDetection\Drivers\TwoCaptcha;
use Weijiajia\TencentUrlDetection\Response as TencentResponse;

// 由于 TwoCaptcha 驱动内部直接实例化 \TwoCaptcha\TwoCaptcha，我们无法直接 Mock 它。
// 测试将重点放在 Saloon 请求的模拟上，假设 ticket 和 randstr 已通过某种方式获得。

test('twocaptcha driver can check safe url', function () {
    $mockClient = new MockClient([
        MockResponse::make([
            'data' => [
                'retcode' => 0,
                'results' => [
                    'url' => 'safe.example.com',
                    'whitetype' => 2, // 2 表示异常
                    'WordingTitle' => '风险提示',
                    'Wording' => '这是一个风险网站',
                    'detect_time' => (string) time(),
                    'eviltype' => '0',
                    'certify' => 0,
                    'isDomainICPOk' => 0,
                ],
            ],
            'reCode' => 0,
        ], 200),
    ]);

    // 创建 \TwoCaptcha\TwoCaptcha 的模拟对象，并设置期望和返回值
    $solverMock = $this->createMock(\TwoCaptcha\TwoCaptcha::class);
    $solverMock->expects($this->once()) // 在此设置期望
        ->method('tencent')
        ->willReturn((object) ['code' => json_encode(['ticket' => 'fake_ticket', 'randstr' => 'fake_randstr'])])
    ;

    $driver = new TwoCaptcha($solverMock, 'test_appid');
    $driver->withMockClient($mockClient);

    $urlToCheck = 'https://safe.example.com';

    $response = $driver->check($urlToCheck);

    expect($response)->toBeInstanceOf(TencentResponse::class);
    expect($response->getUrl())->toBe($urlToCheck);
    // 用户修改的期望：
    expect($response->isWeChatRiskWarning())->toBeTrue();
    expect($response->getWordingTitle())->toBe('风险提示');
    expect($response->getWording())->toBe('这是一个风险网站');

    $mockClient->assertSent(function ($request, $response) use ($urlToCheck) {
        return $request->query()->get('url') === $urlToCheck
            && 'fake_ticket' === $request->query()->get('ticket')
            && 'fake_randstr' === $request->query()->get('randstr');
    });
});

test('twocaptcha driver can check risky url', function () {
    $mockClient = new MockClient([
        MockResponse::make([
            'data' => [
                'retcode' => 0,
                'results' => [
                    'url' => 'risky.example.com',
                    'whitetype' => 1, // 1 通常表示安全
                    'detect_time' => (string) time(),
                    'eviltype' => '2709',
                    'certify' => 0,
                    'isDomainICPOk' => 0,
                ],
            ],
            'reCode' => 0,
        ], 200),
    ]);

    $solverMock = $this->createMock(\TwoCaptcha\TwoCaptcha::class);
    $solverMock->expects($this->once()) // 在此设置期望
        ->method('tencent')
        ->willReturn((object) ['code' => json_encode(['ticket' => 'fake_ticket_risk', 'randstr' => 'fake_randstr_risk'])])
    ;

    $driver = new TwoCaptcha($solverMock, 'test_appid');
    $driver->withMockClient($mockClient);

    $urlToCheck = 'https://risky.example.com';

    $response = $driver->check($urlToCheck);

    expect($response)->toBeInstanceOf(TencentResponse::class);
    expect($response->getUrl())->toBe($urlToCheck);
    // 用户修改的期望：
    expect($response->isWeChatRiskWarning())->toBeFalse();
    expect($response->getWordingTitle())->toBeNull(); // 用户修改
    expect($response->getWording())->toBeNull(); // 用户修改

    $mockClient->assertSent(function ($request, $response) use ($urlToCheck) {
        return $request->query()->get('url') === $urlToCheck
            && 'fake_ticket_risk' === $request->query()->get('ticket')
            && 'fake_randstr_risk' === $request->query()->get('randstr');
    });
});

test('twocaptcha driver throws exception on api error', function () {
    $mockClient = new MockClient([
        MockResponse::make('Server Error', 500),
    ]);

    $solverMock = $this->createMock(\TwoCaptcha\TwoCaptcha::class);
    $solverMock->expects($this->once()) // 在此设置期望
        ->method('tencent')
        ->willReturn((object) ['code' => json_encode(['ticket' => 'fake_ticket_error', 'randstr' => 'fake_randstr_error'])])
    ;

    $driver = new TwoCaptcha($solverMock, 'test_appid');
    $driver->withMockClient($mockClient);

    $urlToCheck = 'https://error.example.com';

    expect(fn () => $driver->check($urlToCheck))->toThrow(RequestException::class);

    $mockClient->assertSent(function ($request, $response) use ($urlToCheck) {
        return $request->query()->get('url') === $urlToCheck
            && 'fake_ticket_error' === $request->query()->get('ticket')
            && 'fake_randstr_error' === $request->query()->get('randstr');
    });
});
