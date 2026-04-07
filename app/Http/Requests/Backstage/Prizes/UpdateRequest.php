<?php

namespace App\Http\Requests\Backstage\Prizes;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|max:255',
            'description' => 'sometimes',
            'image_file' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048',
            'weight' => 'required|numeric|between:0.01,99.99',
            'starts_at' => 'required|date_format:d-m-Y H:i:s',
            'ends_at' => 'required|date_format:d-m-Y H:i:s',
            'segment' => 'required|in:low,med,high',
        ];
    }
}
