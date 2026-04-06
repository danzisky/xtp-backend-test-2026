<?php

namespace App\Services\Game;

use App\Data\Game\BoardPlanData;
use App\Data\Game\GameContextData;
use App\Models\Prize;
use DomainException;
use Illuminate\Support\Collection;

final class GameBoardPlannerService
{
    private const BOARD_SIZE = 5;
    private const WINNING_COUNT = 3;

    public function __construct(
        private PrizeSelectorService $prizeSelector,
        private PrizeAvailabilityService $prizeAvailability
    ) {}

    public function plan(GameContextData $context): BoardPlanData {
        $eligiblePrizes = $this->prizeSelector->getEligiblePrizes($context->campaign, $context->segment);

        if ($eligiblePrizes->isEmpty()) {
            throw new DomainException('No eligible prizes found for this campaign and segment.');
        }

        $canBuildLosingBoard = ($eligiblePrizes->count() * 2) >= (self::BOARD_SIZE * self::BOARD_SIZE);

        $isWinning = random_int(0, 1) === 1;

        $winningPrize = $isWinning
            ? $this->prizeSelector->selectWeightedPrize($context->campaign, $context->segment)
            : null;

        if (! $winningPrize) {
            $isWinning = false;
        }

        if ($isWinning && ! $this->prizeAvailability->isPrizeAvailable($winningPrize, $context->campaign)) {
            $isWinning = false;
        }

        if (! $isWinning && ! $canBuildLosingBoard) {
            $isWinning = true;
            $winningPrize = $this->prizeSelector->selectWeightedPrize($context->campaign, $context->segment);

            if (! $winningPrize || ! $this->prizeAvailability->isPrizeAvailable($winningPrize, $context->campaign)) {
                throw new DomainException('Unable to plan board with current prize constraints.');
            }
        }

        return $isWinning
            ? $this->buildWinningBoard($eligiblePrizes, $winningPrize)
            : $this->buildLosingBoard($eligiblePrizes);
    }

    private function buildWinningBoard(Collection $eligiblePrizes, Prize $winningPrize): BoardPlanData
    {
        $fillerPrizeIds = $eligiblePrizes
            ->where('id', '!=', $winningPrize->id)
            ->pluck('id')
            ->values();

        if ($fillerPrizeIds->isEmpty()) {
            throw new DomainException('Unable to build winning board with no filler prizes.');
        }

        if (($fillerPrizeIds->count() * 2) < ((self::BOARD_SIZE * self::BOARD_SIZE) - self::WINNING_COUNT)) {
            throw new DomainException('Insufficient filler prizes to build a valid winning board.');
        }

        $board = $this->sampleNTiles(
            $fillerPrizeIds,
            (self::BOARD_SIZE * self::BOARD_SIZE) - self::WINNING_COUNT,
            2,
            false
        );

        $board = $board
            ->concat(collect(array_fill(0, self::WINNING_COUNT, $winningPrize->id)))
            ->shuffle()
            ->values();

        return new BoardPlanData(
            isWinner: true,
            winningPrizeId: $winningPrize->id,
            tiles: $board->toArray()
        );
    }

    public function buildLosingBoard(Collection $eligiblePrizes): BoardPlanData
    {
        $prizeIds = $eligiblePrizes->pluck('id')->values();
        $board = $this->sampleNTiles($prizeIds, self::BOARD_SIZE * self::BOARD_SIZE, 2, false);

        return new BoardPlanData(
            isWinner: false,
            winningPrizeId: null,
            tiles: $board->toArray()
        );
    }

    /**
     * Return a collection of $n prize IDs sampled from the eligible prizes, allowing duplicates to ensure we can fill the board.
     *
     * @param Collection $eligiblePrizes
     * @param integer $n
     * @return Collection
     */
    private function sampleNTiles(
        Collection $eligiblePrizes,
        int $n,
        int $maxPerPrize,
        bool $allowOverflow
    ): Collection {
        $counts = [];
        $result = [];

        for ($i = 0; $i < $n; $i++) {
            $available = $eligiblePrizes
                ->filter(fn (int $prizeId) => ($counts[$prizeId] ?? 0) < $maxPerPrize)
                ->values();

            if ($available->isEmpty()) {
                if (! $allowOverflow) {
                    throw new DomainException('Insufficient prize variety to build board safely.');
                }

                $available = $eligiblePrizes;
            }

            $pickedPrizeId = (int) $available->random();
            $result[] = $pickedPrizeId;
            $counts[$pickedPrizeId] = ($counts[$pickedPrizeId] ?? 0) + 1;
        }

        return collect($result);
    }
}
