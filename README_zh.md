# PHP 腾讯 URL 安全检测库

[![Packagist 最新版本](https://img.shields.io/packagist/v/weijiajia/tencent-url-detection.svg?style=flat-square)](https://packagist.org/packages/weijiajia/tencent-url-detection)
[![软件许可证](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![PHP 版本要求](https://img.shields.io/packagist/php-v/weijiajia/tencent-url-detection.svg?style=flat-square)](https://packagist.org/packages/weijiajia/tencent-url-detection)

一个 PHP 库，用于使用腾讯 URL 安全 API 检测 URL 的安全性。它提供了一个简单的接口来检查 URL 是否被腾讯视为有风险（常用于微信和 QQ 的链接预览）。此库支持多种驱动程序，包括直接 API 调用以及与 2Captcha 等验证码识别服务的集成。

## 特性

- 轻松集成腾讯的 URL 安全检测功能。
- 支持多种驱动：
  - `CgiUrlsecQq`：通过 `cgi.urlsec.qq.com` 直接检测（可能需要处理验证码）。
  - `Rrbay`：使用 `rrbay.com` 服务进行 URL 检测。
  - `TwoCaptcha`：集成 `2captcha.com` 服务，在检测前解决腾讯的验证码。
- 基于 [SaloonPHP](https://docs.saloon.dev/) 构建，提供强大的 HTTP 请求功能。
- PSR-4 自动加载和 PSR-12 编码规范（通过 PHP CS Fixer 强制执行）。
- 使用 [PestPHP](https://pestphp.com/) 进行全面的单元和特性测试。

## 环境要求

- PHP ^8.1
- Composer

## 安装

您可以通过 Composer 安装此包：

```bash
composer require weijiajia/tencent-url-detection
```

## Laravel 集成 (可选)

本包包含一个服务提供者，以便轻松集成到 Laravel 框架中。

1.  **服务提供者自动发现：**
    Laravel 会自动发现并注册 `Weijiajia\TencentUrlDetection\TencentUrlDetectionServiceProvider`。

2.  **发布配置文件 (推荐)：**
    要自定义默认驱动和特定驱动的设置（如 API 密钥），请发布配置文件：

    ```bash
    php artisan vendor:publish --provider="Weijiajia\TencentUrlDetection\TencentUrlDetectionServiceProvider" --tag="config"
    ```

    这会在您的应用中创建一个 `config/tencent-url-detection.php` 文件。您应在此文件中填写您的 API 密钥和偏好设置，并且如果其中包含敏感凭据，请将其添加到您的 `.gitignore` 文件中以确保安全。

3.  **在 Laravel 中使用：**
    您可以从服务容器中解析 `DriversManager` 或特定的驱动，或者使用 Facade (如果您选择创建一个)。

    ```php
    use Illuminate\Http\Request;
    use Weijiajia\TencentUrlDetection\DriversManager;
    use Weijiajia\TencentUrlDetection\Contracts\Driver;

    // 控制器中使用依赖注入 DriversManager 的示例
    class UrlCheckController extends Controller
    {
        protected $urlDetectionManager;

        public function __construct(DriversManager $urlDetectionManager)
        {
            $this->urlDetectionManager = $urlDetectionManager;
        }

        public function check(Request $request)
        {
            $urlToCkeck = $request->input('url', 'https://example.com');

            // 获取在 config/tencent-url-detection.php 中配置的默认驱动
            $driver = $this->urlDetectionManager->driver();
            // 或者指定一个驱动：
            // $driver = $this->urlDetectionManager->driver('two_captcha');

            $response = $driver->check($urlToCkeck);

            if ($response->isWeChatRiskWarning()) {
                return response()->json([
                    'url' => $urlToCkeck,
                    'is_risky' => true,
                    'title' => $response->getWordingTitle(),
                    'message' => $response->getWording()
                ]);
            } else {
                return response()->json([
                    'url' => $urlToCkeck,
                    'is_risky' => false,
                    'message' => 'URL 看起来是安全的。'
                ]);
            }
        }
    }

    // 或者，直接解析特定的驱动 (对于默认用法不太常见)
    // $twoCaptchaDriver = app(\Weijiajia\TencentUrlDetection\Drivers\TwoCaptcha::class);
    ```

    **访问配置：**
    通过 `DriversManager` 实例化驱动时，它们将自动使用发布到 `config/tencent-url-detection.php` 的配置。

## 独立使用 (非 Laravel 项目)

首先，您需要选择并实例化一个驱动。响应对象将告知您 URL 是否被认为是安全的，并提供来自腾讯的任何警告信息。

```php
use Weijiajia\TencentUrlDetection\Drivers\CgiUrlsecQq;
use Weijiajia\TencentUrlDetection\Drivers\Rrbay;
use Weijiajia\TencentUrlDetection\Drivers\TwoCaptcha as TwoCaptchaDriver; // 使用别名以避免冲突
use TwoCaptcha\TwoCaptcha; // 2Captcha 验证码服务

$urlToCkeck = 'https://example.com';

// 示例 1: 使用 CgiUrlsecQq (频繁使用时可能需要验证码)
$cgiDriver = new CgiUrlsecQq();
$response = $cgiDriver->check($urlToCkeck);

if ($response->isWeChatRiskWarning()) {
    echo "URL 有风险: " . $response->getWordingTitle() . " - " . $response->getWording();
} else {
    echo "URL 看起来是安全的。";
}

// 示例 2: 使用 Rrbay
// 将 'YOUR_RRBAY_KEY' 替换为您的 Rrbay API 密钥
$rrbayKey = 'YOUR_RRBAY_KEY';
$rrbayDriver = new Rrbay($rrbayKey);
$responseRrbay = $rrbayDriver->check($urlToCkeck);

if ($responseRrbay->isWeChatRiskWarning()) {
    echo "Rrbay: URL 有风险。";
} else {
    echo "Rrbay: URL 看起来是安全的。";
}

// 示例 3: 使用 TwoCaptcha 驱动
// 将 'YOUR_2CAPTCHA_API_KEY' 替换为您的 2Captcha API 密钥
$twoCaptchaApiKey = 'YOUR_2CAPTCHA_API_KEY';
$tencentCaptchaAppId = '2046626881'; // 默认腾讯验证码 appid, 可以覆盖

$captchaSolver = new TwoCaptcha($twoCaptchaApiKey);
$twoCaptchaDriver = new TwoCaptchaDriver($captchaSolver, $tencentCaptchaAppId);

$responseTwoCaptcha = $twoCaptchaDriver->check($urlToCkeck);

if ($responseTwoCaptcha->isWeChatRiskWarning()) {
    echo "2Captcha: URL 有风险: " . $responseTwoCaptcha->getWordingTitle() . " - " . $responseTwoCaptcha->getWording();
} else {
    echo "2Captcha: URL 看起来是安全的。";
}

// 如果需要，访问原始的 Saloon 响应对象
$saloonResponse = $response->getOriginalResponse();
// $rawBody = (string) $saloonResponse->getBody();
// $jsonData = $saloonResponse->json();

```

### 响应对象

`check()` 方法返回一个 `Weijiajia\TencentUrlDetection\Response` 对象，包含以下方法：

- `getUrl(): string`: 返回被检测的 URL。
- `isSafe(): bool`: 如果 URL 被认为是安全的，则返回 `true`，否则返回 `false`。
- `isWeChatRiskWarning(): bool`: 如果腾讯将该 URL 标记为风险（与 `isSafe()` 相反），则返回 `true`。
- `getWordingTitle(): ?string`: 返回腾讯警告信息的标题（如果有）。
- `getWording(): ?string`: 返回腾讯详细的警告信息（如果有）。
- `getOriginalResponse(): \Saloon\Http\Response`: 返回原始的 Saloon 响应对象，用于高级用例。

## 测试

本项目使用 PestPHP 进行测试。要运行测试：

```bash
# 运行所有测试
composer test

# 运行测试并生成覆盖率报告 (需要 Xdebug 或 PCOV)
composer test-coverage
```

## 代码风格

本项目使用 PHP CS Fixer 来强制执行 PSR-12 编码规范。

```bash
# 检查代码风格问题
composer cs-check

# 自动修复代码风格问题
composer cs-fix
```

## 如何贡献

欢迎贡献！如果您发现任何错误或有功能请求，请随时提交 Pull Request 或创建 Issue。

1.  Fork 本仓库。
2.  创建您的功能分支 (`git checkout -b feature/your-amazing-feature`)。
3.  提交您的更改 (`git commit -m 'Add some amazing feature'`)。
4.  将分支推送到远程仓库 (`git push origin feature/your-amazing-feature`)。
5.  创建一个 Pull Request。

请确保您的代码符合编码规范，并且所有测试都通过。

## 许可证

MIT 许可证 (MIT)。详情请参阅 [许可证文件](LICENSE.md)。
