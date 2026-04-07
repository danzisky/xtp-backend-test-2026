<?php

namespace App\Http\Requests\Backstage\Campaigns;

use App\Enums\PrizeSegment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required',
            'timezone' => 'required',
            'matches_to_win' => 'required|integer|min:2',
            'max_tries' => 'required|integer|min:1',
            'starts_at' => 'required',
            'ends_at' => 'required',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateBoardConstraints($validator);
        });
    }

    private function validateBoardConstraints(Validator $validator): void
    {
        if ($validator->errors()->has('matches_to_win') || $validator->errors()->has('max_tries')) {
            return;
        }

        $matchesToWin = (int) $this->input('matches_to_win');
        $maxTries = (int) $this->input('max_tries');

        if ($matchesToWin <= 1 || $maxTries <= 0) {
            return;
        }

        if ($matchesToWin > $maxTries) {
            $validator->errors()->add(
                'matches_to_win',
                'Matches to win must be less than or equal to max tries.'
            );

            return;
        }

        $boardSize = max(1, (int) config('scratchgame.boardsize', 5));
        $maxSupportedTries = $boardSize * $boardSize;

        if ($maxTries > $maxSupportedTries) {
            $validator->errors()->add(
                'max_tries',
                "Configured board size ({$boardSize}x{$boardSize}) supports at most {$maxSupportedTries} tries."
            );

            return;
        }

        $campaign = $this->route('campaign');
        if (! $campaign) {
            return;
        }

        $requiredPrizes = $this->minimumEligiblePrizesRequired($maxTries, $matchesToWin);
        $maxPerPrize = $matchesToWin - 1;

        // Count prizes per segment; fill in 0 for segments that have no prizes at all.
        $segmentCounts = $campaign->prizes()
            ->selectRaw('segment, COUNT(*) as count')
            ->groupBy('segment')
            ->pluck('count', 'segment');

        foreach (PrizeSegment::cases() as $segment) {
            if (! $segmentCounts->has($segment->value)) {
                $segmentCounts->put($segment->value, 0);
            }
        }

        // The bottleneck is the segment with the fewest prizes — the worst case that any player could land in.
        $bottleneckSegment = $segmentCounts->sortBy(fn ($count) => $count)->keys()->first();
        $bottleneckCount   = $segmentCounts->min();

        if ($bottleneckCount < $requiredPrizes) {
            $validator->errors()->add(
                'matches_to_win',
                "Segment '{$bottleneckSegment}' only has {$bottleneckCount} prize(s), but at least {$requiredPrizes} are required "
                . "for {$maxTries} tries with {$matchesToWin} matches to win (each prize may appear at most {$maxPerPrize} times). "
                . "Add more prizes to the '{$bottleneckSegment}' segment or lower the number of tries / matches to win."
            );
        }
    }

    private function minimumEligiblePrizesRequired(int $maxTries, int $matchesToWin): int
    {
        $requiredTiles = (int) ceil(sqrt($maxTries));
        $requiredBoardTiles = $requiredTiles * $requiredTiles;
        $maxPerPrize = max(1, $matchesToWin - 1);

        return (int) ceil($requiredBoardTiles / $maxPerPrize);
    }
}
