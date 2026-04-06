<?php

namespace App\Http\Requests\Backstage\Campaigns;

use Illuminate\Foundation\Http\FormRequest;

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
            'matches_to_win' => 'required|integer|min:2|max:5',
            'max_tries' => 'required|integer|min:1|max:25',
            'starts_at' => 'required',
            'ends_at' => 'required',
        ];
    }
}
