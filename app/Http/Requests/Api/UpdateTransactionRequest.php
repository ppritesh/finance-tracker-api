<?php

namespace App\Http\Requests\Api;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'type' => ['required', Rule::in([Transaction::TYPE_CREDIT_GIVEN, Transaction::TYPE_EXPENSE])],
            'personId' => [
                Rule::requiredIf(fn () => $this->input('type') === Transaction::TYPE_CREDIT_GIVEN),
                'nullable',
                'integer',
                Rule::exists('persons', 'id')->where('user_id', $userId),
            ],
            'note' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'transactionDate' => ['required', 'date'],
        ];
    }
}
