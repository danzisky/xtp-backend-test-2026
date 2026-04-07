<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class FlipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $boardSize = max(1, (int) config('scratchgame.boardsize', 5));
        return [
            'gameId' => 'required|integer|min:1|exists:games,id',
            'tileIndex' => "required|integer|min:0|max:" . ($boardSize * $boardSize - 1),
        ];
    }

    public function gameId(): int
    {
        return (int) $this->validated('gameId');
    }

    public function tileIndex(): int
    {
        return (int) $this->validated('tileIndex');
    }
}