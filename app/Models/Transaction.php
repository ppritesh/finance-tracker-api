<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    public const TYPE_CREDIT_GIVEN = 'credit_given';

    public const TYPE_EXPENSE = 'expense';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_PAID = 'paid';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'person_id',
        'type',
        'note',
        'amount',
        'transaction_date',
        'status',
        'received_on',
        'received_note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
            'received_on' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return array<string, mixed> */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'personId' => $this->person_id,
            'personName' => $this->person?->name,
            'note' => $this->note,
            'amount' => (float) $this->amount,
            'transactionDate' => $this->transaction_date?->format('Y-m-d'),
            'status' => $this->status,
            'receivedOn' => $this->received_on?->format('Y-m-d'),
            'receivedNote' => $this->received_note,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
