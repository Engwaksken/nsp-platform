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

    /** The same result with emoji, pictographs and stray Markdown symbols removed from the text. */
    public function cleaned(): self
    {
        $text = (string) preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{1F1E6}-\x{1F1FF}\x{FE0F}\x{200D}\x{20E3}]/u', '', $this->text);
        $text = (string) preg_replace(['/\*\*|__/', '/^\s{0,3}#{1,6}\s+/m', '/^(\s*)[*•]\s+/m', '/[ \t]+$/m'], ['', '', '$1- ', ''], $text);

        return new self($this->status, trim($text), $this->model, $this->inputTokens, $this->outputTokens, $this->error);
    }

    public function ok(): bool
    {
        return $this->status === 'ok' && trim($this->text) !== '';
    }
}
