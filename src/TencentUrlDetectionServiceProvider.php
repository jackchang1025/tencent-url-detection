<?php

namespace Weijiajia\TencentUrlDetection;

use Illuminate\Support\ServiceProvider;

class TencentUrlDetectionServiceProvider extends ServiceProvider
{
    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerConfig();
    }

    /**
     * Register the service provider.
     */
    public function register(): void {}

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
        $configPath = __DIR__.'/../config/tencent-url-detection.php';

        $this->publishes([
            $configPath => config_path('tencent-url-detection.php'),
        ], 'config');

        $this->mergeConfigFrom($configPath, 'tencent-url-detection');
    }
}
