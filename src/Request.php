<?php

namespace Weijiajia\TencentUrlDetection;

use Saloon\Http\SoloRequest;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use Weijiajia\SaloonphpLogsPlugin\Contracts\HasLoggerInterface;
use Weijiajia\SaloonphpLogsPlugin\HasLogger;
use Weijiajia\TencentUrlDetection\Contracts\Driver;

abstract class Request extends SoloRequest implements HasLoggerInterface, Driver
{
    use HasLogger;
    use AlwaysThrowOnErrors;
}
