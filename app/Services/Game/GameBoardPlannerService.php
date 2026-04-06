<?php

namespace App\Services\Game;

use App\Data\Game\BoardPlanData;
use App\Data\Game\GameContextData;
use App\Models\Prize;
use DomainException;
use Illuminate\Support\Collection;

final class GameBoardPlannerService
{
    public function __construct(
        private PrizeSelectorService $prizeSelector,
        private PrizeAvailabilityService $prizeAvailability
    ) {}

    public function plan(GameContextData $context): BoardPlanData {
        $campaign = $context->campaign;
        $segment = $context->segment;
        $matchesToWin = $campaign->matches_to_win;
        $maxAppearancePerPrize = $matchesToWin - 1;
        $maxTries = $campaign->max_tries;

        $eligiblePrizes = $this->prizeSelector->getEligiblePrizes($campaign, $segment);

        // Calculate board size required based on eligible prizes and max appearances to ensure we can fill the board without running out of prizes.
        $boardSize = min(config('scratchgame.boardsize', 5), (int) floor(sqrt($eligiblePrizes->count() * $maxAppearancePerPrize)));

        if (($boardSize * $boardSize) < $maxTries) {
            throw new DomainException('Board size is too small to accommodate all tries with the given prize constraints.');
        }

        if ($eligiblePrizes->isEmpty()) {
            throw new DomainException('No eligible prizes found for this campaign and segment.');
        }

        $canBuildLosingBoard = ($eligiblePrizes->count() * $maxAppearancePerPrize) >= ($boardSize * $boardSize);

        $isWinning = random_int(0, 1) === 1;

        $winningPrize = $isWinning
            ? $this->prizeSelector->selectWeightedPrize($campaign, $segment)
            : null;

        if (! $winningPrize) {
            $isWinning = false;
        }

        if ($isWinning && ! $this->prizeAvailability->isPrizeAvailable($winningPrize, $campaign)) {
            $isWinning = false;
        }

        if (! $isWinning && ! $canBuildLosingBoard) {
            $isWinning = true;
            $winningPrize = $this->prizeSelector->selectWeightedPrize($campaign, $segment);

            if (! $winningPrize || ! $this->prizeAvailability->isPrizeAvailable($winningPrize, $campaign)) {
                throw new DomainException('Unable to plan board with current prize constraints.');
            }
        }

        return $isWinning
            ? $this->buildWinningBoard($eligiblePrizes, $winningPrize, $boardSize, $matchesToWin)
            : $this->buildLosingBoard($eligiblePrizes, $boardSize, $matchesToWin);
    }

    private function buildWinningBoard(Collection $eligiblePrizes, Prize $winningPrize, int $boardSize, int $matchesToWin): BoardPlanData
    {
        $fillerPrizeIds = $eligiblePrizes
        ->where('id', '!=', $winningPrize->id)
            ->pluck('id')
            ->values();
            
        if ($fillerPrizeIds->isEmpty()) {
            throw new DomainException('Unable to build winning board with no filler prizes.');
            }

        $maxFillerAppearances = $matchesToWin - 1;
        if (($fillerPrizeIds->count() * $maxFillerAppearances) < (($boardSize * $boardSize) - $matchesToWin)) {
            throw new DomainException('Insufficient filler prizes to build a valid winning board.');
        }

        $board = $this->sampleNTiles(
            $fillerPrizeIds,
            ($boardSize * $boardSize) - $matchesToWin,
            $maxFillerAppearances,
        );

        $board = $board
            ->concat(collect(array_fill(0, $matchesToWin, $winningPrize->id)))
            ->shuffle()
            ->values();

        return new BoardPlanData(
            isWinner: true,
            winningPrizeId: $winningPrize->id,
            tiles: $board->toArray()
        );
    }

    public function buildLosingBoard(Collection $eligiblePrizes, int $boardSize, int $matchesToWin): BoardPlanData
    {
        $prizeIds = $eligiblePrizes->pluck('id')->values();
        $maxFillerAppearances = $matchesToWin - 1;
        $board = $this->sampleNTiles($prizeIds, $boardSize * $boardSize, $maxFillerAppearances);

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
     * @param integer $maxPerPrize Maximum number of times a single prize can appear in the result before allowing overflow.
     * @return Collection
     */
    private function sampleNTiles(
        Collection $eligiblePrizes,
        int $n,
        int $maxPerPrize,
    ): Collection {
        $sample = $eligiblePrizes->unique();

        for ($i = 0; $i < $maxPerPrize; $i++) { // Allow up to $maxPerPrize duplicates of each prize
            $sample = $sample->merge($eligiblePrizes)->shuffle();
        }

        return $sample->shuffle()->take($n)->values();
    }
}
