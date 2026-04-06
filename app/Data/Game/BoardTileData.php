<?php

namespace App\Data\Game;

final class BoardTileData
{
    public function __construct(
        public readonly int $tileIndex,
        public readonly int $prizeId,
        public readonly string $tileImage,
    )   {}
}