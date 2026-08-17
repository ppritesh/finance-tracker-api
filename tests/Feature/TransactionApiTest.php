<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(User $user): array
    {
        $token = $user->createToken('test')->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_user_can_create_credit_and_expense_transactions(): void
    {
        $user = User::factory()->create();
        $person = Person::query()->create([
            'user_id' => $user->id,
            'name' => 'Ravi',
        ]);

        $credit = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/transactions', [
                'type' => Transaction::TYPE_CREDIT_GIVEN,
                'personId' => $person->id,
                'note' => 'Loan for bike',
                'amount' => 5000,
                'transactionDate' => '2026-08-01',
            ]);

        $credit->assertCreated()
            ->assertJsonPath('transaction.status', Transaction::STATUS_PENDING)
            ->assertJsonPath('transaction.personName', 'Ravi');

        $expense = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/transactions', [
                'type' => Transaction::TYPE_EXPENSE,
                'note' => 'Groceries',
                'amount' => 1200.50,
                'transactionDate' => '2026-08-02',
            ]);

        $expense->assertCreated()
            ->assertJsonPath('transaction.status', Transaction::STATUS_PAID);
    }

    public function test_credit_requires_person(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/transactions', [
                'type' => Transaction::TYPE_CREDIT_GIVEN,
                'amount' => 500,
                'transactionDate' => '2026-08-01',
            ])
            ->assertUnprocessable();
    }

    public function test_user_can_mark_credit_received(): void
    {
        $user = User::factory()->create();
        $person = Person::query()->create([
            'user_id' => $user->id,
            'name' => 'Amit',
        ]);

        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'person_id' => $person->id,
            'type' => Transaction::TYPE_CREDIT_GIVEN,
            'amount' => 3000,
            'transaction_date' => '2026-08-01',
            'status' => Transaction::STATUS_PENDING,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/transactions/{$transaction->id}/mark-received", [
                'receivedOn' => '2026-08-10',
                'receivedNote' => 'UPI payment',
            ])
            ->assertOk()
            ->assertJsonPath('transaction.status', Transaction::STATUS_RECEIVED)
            ->assertJsonPath('transaction.receivedNote', 'UPI payment');
    }

    public function test_summary_returns_totals(): void
    {
        $user = User::factory()->create();
        $person = Person::query()->create([
            'user_id' => $user->id,
            'name' => 'Suresh',
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'person_id' => $person->id,
            'type' => Transaction::TYPE_CREDIT_GIVEN,
            'amount' => 2000,
            'transaction_date' => now()->toDateString(),
            'status' => Transaction::STATUS_PENDING,
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'person_id' => $person->id,
            'type' => Transaction::TYPE_CREDIT_GIVEN,
            'amount' => 1000,
            'transaction_date' => now()->toDateString(),
            'status' => Transaction::STATUS_RECEIVED,
            'received_on' => now()->toDateString(),
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'type' => Transaction::TYPE_EXPENSE,
            'amount' => 500,
            'transaction_date' => now()->toDateString(),
            'status' => Transaction::STATUS_PAID,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/summary')
            ->assertOk()
            ->assertJsonPath('pendingCreditTotal', 2000)
            ->assertJsonPath('receivedCreditTotal', 1000)
            ->assertJsonPath('expenseTotalThisMonth', 500);
    }

    public function test_person_cannot_be_deleted_with_transactions(): void
    {
        $user = User::factory()->create();
        $person = Person::query()->create([
            'user_id' => $user->id,
            'name' => 'Kiran',
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'person_id' => $person->id,
            'type' => Transaction::TYPE_CREDIT_GIVEN,
            'amount' => 100,
            'transaction_date' => now()->toDateString(),
            'status' => Transaction::STATUS_PENDING,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/persons/{$person->id}")
            ->assertStatus(422);
    }
}
