<?php

namespace Weijiajia\TencentUrlDetection\Contracts;

use Weijiajia\TencentUrlDetection\Response;

interface Driver
{
    public function check(string $url): Response;
}
