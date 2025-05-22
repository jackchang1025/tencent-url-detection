<?php

namespace Weijiajia\TencentUrlDetection;

use Saloon\Http\SoloRequest;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use Weijiajia\SaloonphpLogsPlugin\Contracts\HasLoggerInterface;
use Weijiajia\SaloonphpLogsPlugin\HasLogger;

abstract class Request extends SoloRequest implements HasLoggerInterface
{
    use HasLogger;
    use AlwaysThrowOnErrors;

    abstract public function check(string $url): Response;
}
