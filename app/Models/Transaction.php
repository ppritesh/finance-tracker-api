<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    public const TYPE_CREDIT_GIVEN = 'credit_given';

    public const TYPE_EXPENSE = 'expense';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PARTIAL = 'partial';

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

    /** @return HasMany<Settlement, $this> */
    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class)->orderBy('received_on')->orderBy('id');
    }

    public function receivedTotal(): float
    {
        return (float) $this->settlements()->sum('amount');
    }

    public function remainingAmount(): float
    {
        return max(0, (float) $this->amount - $this->receivedTotal());
    }

    public function syncPaymentStatus(): void
    {
        $this->unsetRelation('settlements');

        $receivedTotal = $this->receivedTotal();
        $totalAmount = (float) $this->amount;

        if ($receivedTotal <= 0) {
            $this->update([
                'status' => self::STATUS_PENDING,
                'received_on' => null,
                'received_note' => null,
            ]);

            return;
        }

        $latestSettlement = Settlement::query()
            ->where('transaction_id', $this->id)
            ->orderByDesc('received_on')
            ->orderByDesc('id')
            ->first();

        if ($receivedTotal >= $totalAmount) {
            $this->update([
                'status' => self::STATUS_RECEIVED,
                'received_on' => $latestSettlement?->received_on,
                'received_note' => $latestSettlement?->note,
            ]);

            return;
        }

        $this->update([
            'status' => self::STATUS_PARTIAL,
            'received_on' => $latestSettlement?->received_on,
            'received_note' => $latestSettlement?->note,
        ]);
    }

    /** @return array<string, mixed> */
    public function toApiArray(): array
    {
        $receivedTotal = $this->relationLoaded('settlements')
            ? (float) $this->settlements->sum('amount')
            : $this->receivedTotal();

        return [
            'id' => $this->id,
            'type' => $this->type,
            'personId' => $this->person_id,
            'personName' => $this->person?->name,
            'note' => $this->note,
            'amount' => (float) $this->amount,
            'receivedTotal' => $receivedTotal,
            'remainingAmount' => max(0, (float) $this->amount - $receivedTotal),
            'transactionDate' => $this->transaction_date?->format('Y-m-d'),
            'status' => $this->status,
            'receivedOn' => $this->received_on?->format('Y-m-d'),
            'receivedNote' => $this->received_note,
            'settlements' => $this->relationLoaded('settlements')
                ? $this->settlements->map->toApiArray()->values()->all()
                : [],
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
