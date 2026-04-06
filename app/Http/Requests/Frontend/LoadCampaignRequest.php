<?php

namespace App\Http\Requests\Frontend;

use App\Enums\PrizeSegment;
use Illuminate\Foundation\Http\FormRequest;

class LoadCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'a' => trim((string) $this->query('a', '')),
            'segment' => strtolower(trim((string) $this->query('segment', ''))),
        ]);
    }

    public function rules(): array
    {
        return [
            'a' => 'required|string|max:120',
            'segment' => 'required|in:'.implode(',', array_column(PrizeSegment::cases(), 'value')),
        ];
    }

    public function account(): ?string
    {
        return (string) $this->validated('a');
    }

    public function prizeSegment(): ?string
    {
        return (string) $this->validated('segment');
    }
}