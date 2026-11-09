<?php

namespace App\Ai;

/** The calls the assistant makes. Bound to AnthropicAiClient; tests bind a fake. */
interface AiClient
{
    /**
     * @param  string  $system  fixed operator instructions (never contains user text)
     * @param  string  $user  the user turn: context lines plus the founder's text inside delimiters
     */
    public function complete(string $system, string $user): AiResult;

    /** Checks the API key and model without generating text (no token cost). Returns null when it works, otherwise a message for admins. */
    public function check(): ?string;
}
