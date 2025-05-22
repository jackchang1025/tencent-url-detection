# Tencent URL Detection for PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/weijiajia/tencent-url-detection.svg?style=flat-square)](https://packagist.org/packages/weijiajia/tencent-url-detection)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![PHP Version Require](https://img.shields.io/packagist/php-v/weijiajia/tencent-url-detection.svg?style=flat-square)](https://packagist.org/packages/weijiajia/tencent-url-detection)

<!-- [![Build Status](https://img.shields.io/travis/com/weijiajia/tencent-url-detection/main.svg?style=flat-square)](https://travis-ci.com/weijiajia/tencent-url-detection) -->
<!-- [![Coverage Status](https://img.shields.io/coveralls/github/weijiajia/tencent-url-detection/main.svg?style=flat-square)](https://coveralls.io/github/weijiajia/tencent-url-detection?branch=main) -->
<!-- [![Total Downloads](https://img.shields.io/packagist/dt/weijiajia/tencent-url-detection.svg?style=flat-square)](https://packagist.org/packages/weijiajia/tencent-url-detection) -->

A PHP library to detect URL safety using Tencent's URL security API. It provides a simple interface to check if a URL is considered risky by Tencent, often used for WeChat and QQ link previews. This library supports multiple drivers, including direct API calls and integration with captcha solving services like 2Captcha.

## Features

- Easy integration with Tencent's URL safety check.
- Multiple drivers available:
  - `CgiUrlsecQq`: Direct check via `cgi.urlsec.qq.com` (may require captcha solving).
  - `Rrbay`: Uses the `rrbay.com` service for URL checking.
  - `TwoCaptcha`: Integrates with `2captcha.com` to solve Tencent's captchas before checking.
- Built on [SaloonPHP](https://docs.saloon.dev/) for robust HTTP requests.
- PSR-4 autoloading and PSR-12 coding standards (enforced by PHP CS Fixer).
- Comprehensive unit and feature tests using [PestPHP](https://pestphp.com/).

## Requirements

- PHP ^8.1
- Composer

## Installation

You can install the package via Composer:

```bash
composer require weijiajia/tencent-url-detection
```

## Laravel Integration (Optional)

This package includes a Service Provider for easy integration with the Laravel framework.

1.  **Service Provider Auto-Discovery:**
    The `Weijiajia\TencentUrlDetection\TencentUrlDetectionServiceProvider` will be automatically discovered and registered by Laravel.

2.  **Publish Configuration (Recommended):**
    To customize the default driver and driver-specific settings (like API keys), publish the configuration file:

    ```bash
    php artisan vendor:publish --provider="Weijiajia\TencentUrlDetection\TencentUrlDetectionServiceProvider" --tag="config"
    ```

    This will create a `config/tencent-url-detection.php` file in your application. You should fill in your API keys and preferred settings in this file and, for security, add it to your `.gitignore` if it contains sensitive credentials.

3.  **Usage in Laravel:**
    You can resolve the `DriversManager` or a specific driver from the service container, or use the Facade (if you choose to create one).

    ```php
    use Illuminate\Http\Request;
    use Weijiajia\TencentUrlDetection\DriversManager;
    use Weijiajia\TencentUrlDetection\Contracts\Driver;

    // Example in a Controller using Dependency Injection for the Manager
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

            // Get the default driver as configured in config/tencent-url-detection.php
            $driver = $this->urlDetectionManager->driver();
            // Or specify a driver:
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
                    'message' => 'URL seems safe.'
                ]);
            }
        }
    }

    // Alternatively, resolving a specific driver directly (less common for default usage)
    // $twoCaptchaDriver = app(\Weijiajia\TencentUrlDetection\Drivers\TwoCaptcha::class);
    ```

    **Accessing Configuration:**
    The drivers will automatically use the configuration published to `config/tencent-url-detection.php` when instantiated via the `DriversManager`.

## Standalone Usage (Non-Laravel)

First, you need to choose and instantiate a driver. The response object will tell you if the URL is considered safe and provide any warning messages from Tencent.

```php
use Weijiajia\TencentUrlDetection\Drivers\CgiUrlsecQq;
use Weijiajia\TencentUrlDetection\Drivers\Rrbay;
use Weijiajia\TencentUrlDetection\Drivers\TwoCaptcha as TwoCaptchaDriver; // Alias to avoid conflict
use TwoCaptcha\TwoCaptcha; // The 2Captcha solver service

$urlToCkeck = 'https://example.com';

// Example 1: Using CgiUrlsecQq (may require captcha if used frequently)
$cgiDriver = new CgiUrlsecQq();
$response = $cgiDriver->check($urlToCkeck);

if ($response->isWeChatRiskWarning()) {
    echo "URL is risky: " . $response->getWordingTitle() . " - " . $response->getWording();
} else {
    echo "URL seems safe.";
}

// Example 2: Using Rrbay
// Replace 'YOUR_RRBAY_KEY' with your actual Rrbay API key
$rrbayKey = 'YOUR_RRBAY_KEY';
$rrbayDriver = new Rrbay($rrbayKey);
$responseRrbay = $rrbayDriver->check($urlToCkeck);

if ($responseRrbay->isWeChatRiskWarning()) {
    echo "Rrbay: URL is risky.";
} else {
    echo "Rrbay: URL seems safe.";
}

// Example 3: Using TwoCaptcha driver
// Replace 'YOUR_2CAPTCHA_API_KEY' with your actual 2Captcha API key
$twoCaptchaApiKey = 'YOUR_2CAPTCHA_API_KEY';
$tencentCaptchaAppId = '2046626881'; // Default Tencent captcha appid, can be overridden

$captchaSolver = new TwoCaptcha($twoCaptchaApiKey);
$twoCaptchaDriver = new TwoCaptchaDriver($captchaSolver, $tencentCaptchaAppId);

$responseTwoCaptcha = $twoCaptchaDriver->check($urlToCkeck);

if ($responseTwoCaptcha->isWeChatRiskWarning()) {
    echo "2Captcha: URL is risky: " . $responseTwoCaptcha->getWordingTitle() . " - " . $responseTwoCaptcha->getWording();
} else {
    echo "2Captcha: URL seems safe.";
}

// Accessing the original Saloon response if needed
$saloonResponse = $response->getOriginalResponse();
// $rawBody = (string) $saloonResponse->getBody();
// $jsonData = $saloonResponse->json();

```

### Response Object

The `check()` method returns a `Weijiajia\TencentUrlDetection\Response` object with the following methods:

- `getUrl(): string`: Returns the URL that was checked.
- `isSafe(): bool`: Returns `true` if the URL is considered safe, `false` otherwise.
- `isWeChatRiskWarning(): bool`: Returns `true` if Tencent flags it as a risk (opposite of `isSafe()`).
- `getWordingTitle(): ?string`: Returns the title of the warning message from Tencent, if any.
- `getWording(): ?string`: Returns the detailed warning message from Tencent, if any.
- `getOriginalResponse(): \Saloon\Http\Response`: Returns the original Saloon response object for advanced use cases.

## Testing

The package uses PestPHP for testing. To run the tests:

```bash
# Run all tests
composer test

# Run tests with coverage report (requires Xdebug or PCOV)
composer test-coverage
```

## Code Styling

This project uses PHP CS Fixer to enforce PSR-12 coding standards.

```bash
# Check for coding style violations
composer cs-check

# Automatically fix coding style violations
composer cs-fix
```

## Contributing

Contributions are welcome! Please feel free to submit a pull request or create an issue for any bugs or feature requests.

1.  Fork the repository.
2.  Create your feature branch (`
