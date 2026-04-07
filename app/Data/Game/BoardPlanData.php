<?php

namespace App\Data\Game;

final class BoardPlanData
{
    public function __construct(
        public readonly bool $isWinner,
        public readonly ?int $winningPrizeId,
        public readonly array $tiles,
    )   {}
}