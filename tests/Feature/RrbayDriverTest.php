<?php

use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Weijiajia\TencentUrlDetection\Drivers\Rrbay;
use Weijiajia\TencentUrlDetection\Response as TencentResponse;

test('rrbay driver can check safe url', function () {
    $mockClient = new MockClient([
        MockResponse::make(['Code' => '101'], 200), // 101 表示安全
    ]);

    $driver = new Rrbay('test_key');
    $driver->withMockClient($mockClient);

    $urlToCheck = 'https://safe.example.com';

    $response = $driver->check($urlToCheck); // check() internally calls send()

    // 判断是否发送的 参数是否包含 url
    $mockClient->assertSent(function ($request, $response) use ($urlToCheck) {
        return $request->query()->get('url') === $urlToCheck
            && 'test_key' === $request->query()->get('key');
    });

    expect($response)->toBeInstanceOf(TencentResponse::class);
    expect($response->getUrl())->toBe($urlToCheck);
    expect($response->isWeChatRiskWarning())->toBeTrue(); // 101 表示安全，所以风险应为 false
});

test('rrbay driver can check risky url', function () {
    $mockClient = new MockClient([
        MockResponse::make(['Code' => '100'], 200), // 100 表示风险
    ]);

    $driver = new Rrbay('test_key');
    $driver->withMockClient($mockClient);

    $urlToCheck = 'https://risky.example.com';
    $reflection = new ReflectionClass($driver);
    $urlProperty = $reflection->getProperty('url');
    $urlProperty->setAccessible(true);
    $urlProperty->setValue($driver, $urlToCheck);

    $response = $driver->check($urlToCheck);

    expect($response)->toBeInstanceOf(TencentResponse::class);
    expect($response->getUrl())->toBe($urlToCheck);
    expect($response->isWeChatRiskWarning())->toBeFalse(); // 100 表示风险，所以风险应为 true
});

test('rrbay driver throws exception on api error', function () {
    $mockClient = new MockClient([
        MockResponse::make('Server Error', 500),
    ]);

    $driver = new Rrbay('test_key');
    $driver->withMockClient($mockClient);

    $urlToCheck = 'https://error.example.com';
    $reflection = new ReflectionClass($driver);
    $urlProperty = $reflection->getProperty('url');
    $urlProperty->setAccessible(true);
    $urlProperty->setValue($driver, $urlToCheck);

    expect(fn () => $driver->check($urlToCheck))->toThrow(RequestException::class);
});
