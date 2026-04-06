<?php

namespace App\Data\Game;

use App\Models\Campaign;

final class GameContextData
{
    public function __construct(
        public readonly Campaign $campaign,
        public readonly string $account,
        public readonly string $segment,
    )   {}
}