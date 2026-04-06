<?php

namespace App\Data\Game;

final class FlipResultData
{
    public function __construct(
        public readonly string $tileImage,
        public readonly ?string $message = null,
    )   {}

    public function toArray(): array
    {
        return array_filter([
            'tileImage' => $this->tileImage,
            'message' => $this->message,
        ], fn($value) => $value !== null);
    }
}