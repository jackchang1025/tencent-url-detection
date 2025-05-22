<?php

use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Weijiajia\TencentUrlDetection\Drivers\TwoCaptcha;
use Weijiajia\TencentUrlDetection\Response as TencentResponse;
use Weijiajia\TencentUrlDetection\TencentUrlDetectionException;

test('twocaptcha driver can check safe url', function () {
    $mockClient = new MockClient([
        MockResponse::make('jQuery5033140462208196_1747932216482({"data":{"retcode":0,"results":{"url":"demo.com","whitetype":2,"WordingTitle":"风险提示","Wording":"这是一个风险网站","detect_time":"0","eviltype":"0","certify":0,"isDomainICPOk":0}},"reCode":0})
', 200),
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
        MockResponse::make('jQuery5033140462208196_1747932216482({"data":{"retcode":0,"results":{"url":"demo.com","whitetype":1,"WordingTitle":"","Wording":"","detect_time":"0","eviltype":"0","certify":0,"isDomainICPOk":0}},"reCode":0})
', 200),
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
    expect($response->getWordingTitle())->toBe(''); // 用户修改
    expect($response->getWording())->toBe(''); // 用户修改

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

test('twocaptcha driver can check', function () {
    $mockClient = new MockClient([
        MockResponse::make('', 200),
    ]);

    $solverMock = $this->createMock(\TwoCaptcha\TwoCaptcha::class);
    $solverMock->expects($this->once()) // 在此设置期望
        ->method('tencent')
        ->willReturn((object) ['code' => json_encode(['ticket' => 'fake_ticket_risk', 'randstr' => 'fake_randstr_risk'])])
    ;

    $driver = new TwoCaptcha($solverMock, 'test_appid');
    $driver->withMockClient($mockClient);

    $urlToCheck = 'https://risky.example.com';

    expect(fn () => $driver->check($urlToCheck))->toThrow(TencentUrlDetectionException::class);
});

// 修改后的测试：针对 API 返回空响应体的情况
test('twocaptcha driver throws TencentUrlDetectionException for empty api response', function () {
    $mockClient = new MockClient([
        MockResponse::make('', 200), // API 返回空响应体
    ]);

    $solverMock = $this->createMock(\TwoCaptcha\TwoCaptcha::class);
    $solverMock->expects($this->once())
        ->method('tencent')
        ->willReturn((object) ['code' => json_encode(['ticket' => 'fake_ticket_empty_api', 'randstr' => 'fake_randstr_empty_api'])])
    ;

    $driver = new TwoCaptcha($solverMock, 'test_appid');
    $driver->withMockClient($mockClient);

    $urlToCheck = 'https://emptyresponse.example.com';

    expect(fn () => $driver->check($urlToCheck))
        ->toThrow(TencentUrlDetectionException::class, 'Response is not in the expected JSONP format')
    ;
});

// 新增测试用例

// --- 测试 solver 返回数据异常 ---
test('throws TencentUrlDetectionException if solver returns invalid json for code', function () {
    $solverMock = $this->createMock(\TwoCaptcha\TwoCaptcha::class);
    $solverMock->expects($this->once())
        ->method('tencent')
        ->willReturn((object) ['code' => 'this is not a valid json string'])
    ;

    $driver = new TwoCaptcha($solverMock, 'test_appid');
    // 不需要 MockClient，因为会在解析 solver 响应时失败

    $urlToCheck = 'https://solverinvalidjson.example.com';

    expect(fn () => $driver->check($urlToCheck))
        ->toThrow(TencentUrlDetectionException::class, 'Failed to decode JSON response: Syntax error')
    ;
});

test('throws TencentUrlDetectionException if solver returns json missing ticket', function () {
    $solverMock = $this->createMock(\TwoCaptcha\TwoCaptcha::class);
    $solverMock->expects($this->once())
        ->method('tencent')
        ->willReturn((object) ['code' => json_encode(['randstr' => 'fake_randstr_no_ticket'])])
    ;

    $driver = new TwoCaptcha($solverMock, 'test_appid');
    $urlToCheck = 'https://solvermissingticket.example.com';

    expect(fn () => $driver->check($urlToCheck))
        ->toThrow(TencentUrlDetectionException::class, 'Invalid JSON response: missing ticket or randstr')
    ;
});

test('throws TencentUrlDetectionException if solver returns json missing randstr', function () {
    $solverMock = $this->createMock(\TwoCaptcha\TwoCaptcha::class);
    $solverMock->expects($this->once())
        ->method('tencent')
        ->willReturn((object) ['code' => json_encode(['ticket' => 'fake_ticket_no_randstr'])])
    ;

    $driver = new TwoCaptcha($solverMock, 'test_appid');
    $urlToCheck = 'https://solvermissingrandstr.example.com';

    expect(fn () => $driver->check($urlToCheck))
        ->toThrow(TencentUrlDetectionException::class, 'Invalid JSON response: missing ticket or randstr')
    ;
});

// --- 测试主 API 返回 JSONP 格式错误 ---
test('throws TencentUrlDetectionException for jsonp with invalid callback function name', function () {
    $mockClient = new MockClient([
        MockResponse::make('invalid-callback({"data":{}})', 200),
    ]);
    $solverMock = $this->createMock(\TwoCaptcha\TwoCaptcha::class);
    $solverMock->method('tencent')->willReturn((object) ['code' => json_encode(['ticket' => 't', 'randstr' => 'r'])]);
    $driver = new TwoCaptcha($solverMock);
    $driver->withMockClient($mockClient);
    expect(fn () => $driver->check('url'))
        ->toThrow(TencentUrlDetectionException::class, 'Invalid JSONP callback format in response: invalid-callback')
    ;
});

test('throws TencentUrlDetectionException for jsonp missing opening parenthesis', function () {
    $mockClient = new MockClient([
        MockResponse::make('jQueryCallback"data":{}})', 200),
    ]);
    $solverMock = $this->createMock(\TwoCaptcha\TwoCaptcha::class);
    $solverMock->method('tencent')->willReturn((object) ['code' => json_encode(['ticket' => 't', 'randstr' => 'r'])]);
    $driver = new TwoCaptcha($solverMock);
    $driver->withMockClient($mockClient);
    expect(fn () => $driver->check('url'))
        ->toThrow(TencentUrlDetectionException::class, 'Response is not in the expected JSONP format')
    ;
});

test('throws TencentUrlDetectionException for jsonp missing closing parenthesis', function () {
    $mockClient = new MockClient([
        MockResponse::make('jQueryCallback({"data":{}}', 200),
    ]);
    $solverMock = $this->createMock(\TwoCaptcha\TwoCaptcha::class);
    $solverMock->method('tencent')->willReturn((object) ['code' => json_encode(['ticket' => 't', 'randstr' => 'r'])]);
    $driver = new TwoCaptcha($solverMock);
    $driver->withMockClient($mockClient);
    expect(fn () => $driver->check('url'))
        ->toThrow(TencentUrlDetectionException::class, 'Response is not in the expected JSONP format')
    ;
});

test('throws TencentUrlDetectionException for jsonp with invalid internal json structure', function () {
    $mockClient = new MockClient([
        MockResponse::make('jQueryCallback({"data": "missing_brace")', 200),
    ]);
    $solverMock = $this->createMock(\TwoCaptcha\TwoCaptcha::class);
    $solverMock->method('tencent')->willReturn((object) ['code' => json_encode(['ticket' => 't', 'randstr' => 'r'])]);
    $driver = new TwoCaptcha($solverMock);
    $driver->withMockClient($mockClient);
    expect(fn () => $driver->check('url'))
        ->toThrow(TencentUrlDetectionException::class, 'Syntax error while decoding JSON extracted from JSONP: Syntax error')
    ;
});

// --- 测试 API 响应中可选字段缺失的情况 ---
test('handles api response with missing whitetype gracefully', function () {
    $mockClient = new MockClient([
        MockResponse::make('jQueryCallback({"data":{"retcode":0,"results":{"url":"demo.com","WordingTitle":"Title","Wording":"Content"}},"reCode":0})', 200),
    ]);
    $solverMock = $this->createMock(\TwoCaptcha\TwoCaptcha::class);
    $solverMock->method('tencent')->willReturn((object) ['code' => json_encode(['ticket' => 't', 'randstr' => 'r'])]);
    $driver = new TwoCaptcha($solverMock);
    $driver->withMockClient($mockClient);
    $response = $driver->check('https://missingwhitetype.com');

    expect($response->isWeChatRiskWarning())->toBeFalse(); // (null ?? null) === 2 is false
    expect($response->getWordingTitle())->toBe('Title');
    expect($response->getWording())->toBe('Content');
});

test('handles api response with missing WordingTitle gracefully', function () {
    $mockClient = new MockClient([
        MockResponse::make('jQueryCallback({"data":{"retcode":0,"results":{"url":"demo.com","whitetype":1,"Wording":"Content"}},"reCode":0})', 200),
    ]);
    $solverMock = $this->createMock(\TwoCaptcha\TwoCaptcha::class);
    $solverMock->method('tencent')->willReturn((object) ['code' => json_encode(['ticket' => 't', 'randstr' => 'r'])]);
    $driver = new TwoCaptcha($solverMock);
    $driver->withMockClient($mockClient);
    $response = $driver->check('https://missingwordingtitle.com');

    expect($response->isWeChatRiskWarning())->toBeFalse(); // whitetype 1
    expect($response->getWordingTitle())->toBe(''); // Default value
    expect($response->getWording())->toBe('Content');
});

test('handles api response with missing Wording gracefully', function () {
    $mockClient = new MockClient([
        MockResponse::make('jQueryCallback({"data":{"retcode":0,"results":{"url":"demo.com","whitetype":2,"WordingTitle":"Title"}},"reCode":0})', 200),
    ]);
    $solverMock = $this->createMock(\TwoCaptcha\TwoCaptcha::class);
    $solverMock->method('tencent')->willReturn((object) ['code' => json_encode(['ticket' => 't', 'randstr' => 'r'])]);
    $driver = new TwoCaptcha($solverMock);
    $driver->withMockClient($mockClient);
    $response = $driver->check('https://missingwording.com');

    expect($response->isWeChatRiskWarning())->toBeTrue(); // whitetype 2
    expect($response->getWordingTitle())->toBe('Title');
    expect($response->getWording())->toBe(''); // Default value
});

test('handles api response with all optional fields missing gracefully', function () {
    $mockClient = new MockClient([
        MockResponse::make('jQueryCallback({"data":{"retcode":0,"results":{"url":"demo.com"}},"reCode":0})', 200), // Missing whitetype, WordingTitle, Wording
    ]);
    $solverMock = $this->createMock(\TwoCaptcha\TwoCaptcha::class);
    $solverMock->method('tencent')->willReturn((object) ['code' => json_encode(['ticket' => 't', 'randstr' => 'r'])]);
    $driver = new TwoCaptcha($solverMock);
    $driver->withMockClient($mockClient);
    $response = $driver->check('https://allmissing.com');

    expect($response->isWeChatRiskWarning())->toBeFalse();
    expect($response->getWordingTitle())->toBe('');
    expect($response->getWording())->toBe('');
});
