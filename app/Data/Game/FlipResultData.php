<?php

namespace App\Data\Game;

final class FlipResultData
{
    public function __construct(
        public readonly string $tileImage,
        public readonly ?string $message = null,
    )   {}

    /**
     * Convert the flip result data to an array format suitable for JSON serialization, filtering out any null values.
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_filter([
            'tileImage' => $this->tileImage,
            'message' => $this->message,
        ], fn($value) => $value !== null);
    }
}