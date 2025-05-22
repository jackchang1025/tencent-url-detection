<?php

namespace Weijiajia\TencentUrlDetection;

use Saloon\Http\Response as SaloonResponse;

class Response
{
    public function __construct(
        public string $url,
        public bool $IsWeChatRiskWarning,
        public SaloonResponse $response,
        public ?string $wordingTitle = null,
        public ?string $wording = null,
    ) {}

    public function isWeChatRiskWarning(): bool
    {
        return $this->IsWeChatRiskWarning;
    }

    public function getResponse(): SaloonResponse
    {
        return $this->response;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getWordingTitle(): ?string
    {
        return $this->wordingTitle;
    }

    public function getWording(): ?string
    {
        return $this->wording;
    }
}
