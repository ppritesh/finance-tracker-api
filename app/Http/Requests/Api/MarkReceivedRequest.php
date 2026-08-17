<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MarkReceivedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'receivedOn' => ['required', 'date'],
            'receivedNote' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
