<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Settlement extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'transaction_id',
        'amount',
        'received_on',
        'note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_on' => 'date',
        ];
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** @return array<string, mixed> */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'receivedOn' => $this->received_on?->format('Y-m-d'),
            'note' => $this->note,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
