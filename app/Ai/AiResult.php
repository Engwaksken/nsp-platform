<?php

namespace App\Ai;

final class AiResult
{
    /** @param string $status ok, refused or error */
    public function __construct(
        public readonly string $status,
        public readonly string $text = '',
        public readonly ?string $model = null,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly ?string $error = null,
    ) {}

    public function ok(): bool
    {
        return $this->status === 'ok' && trim($this->text) !== '';
    }
}
