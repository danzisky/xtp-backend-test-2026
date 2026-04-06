<?php

namespace App\Http\Requests\Backstage\Campaigns;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRequest extends FormRequest
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
        }

        $boardSize = max(1, (int) config('scratchgame.boardsize', 5));
        $maxSupportedTries = $boardSize * $boardSize;

        if ($maxTries > $maxSupportedTries) {
            $validator->errors()->add(
                'max_tries',
                "Configured board size ({$boardSize}x{$boardSize}) supports at most {$maxSupportedTries} tries."
            );
        }
    }
}
