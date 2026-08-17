<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MarkReceivedRequest;
use App\Http\Requests\Api\RecordPaymentRequest;
use App\Http\Requests\Api\StoreTransactionRequest;
use App\Http\Requests\Api\UpdateTransactionRequest;
use App\Models\Settlement;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()
            ->transactions()
            ->with(['person', 'settlements'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('person_id')) {
            $query->where('person_id', $request->integer('person_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('transaction_date', '>=', $request->string('from')->toString());
        }

        if ($request->filled('to')) {
            $query->whereDate('transaction_date', '<=', $request->string('to')->toString());
        }

        return response()->json([
            'transactions' => $query->get()->map->toApiArray()->values(),
        ]);
    }

    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $type = $request->string('type')->toString();
        $status = $type === Transaction::TYPE_EXPENSE
            ? Transaction::STATUS_PAID
            : Transaction::STATUS_PENDING;

        $transaction = $request->user()->transactions()->create([
            'type' => $type,
            'person_id' => $request->input('personId'),
            'note' => $request->string('note')->trim()->toString() ?: null,
            'amount' => $request->input('amount'),
            'transaction_date' => $request->string('transactionDate')->toString(),
            'status' => $status,
        ]);

        $transaction->load(['person', 'settlements']);

        return response()->json([
            'transaction' => $transaction->toApiArray(),
        ], 201);
    }

    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        $this->authorizeTransaction($request, $transaction);
        $transaction->load(['person', 'settlements']);

        return response()->json([
            'transaction' => $transaction->toApiArray(),
        ]);
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction): JsonResponse
    {
        $this->authorizeTransaction($request, $transaction);

        if ($transaction->type === Transaction::TYPE_CREDIT_GIVEN
            && in_array($transaction->status, [Transaction::STATUS_PARTIAL, Transaction::STATUS_RECEIVED], true)) {
            return response()->json([
                'message' => 'Credit with payments recorded cannot be edited.',
            ], 422);
        }

        $type = $request->string('type')->toString();
        $status = $type === Transaction::TYPE_EXPENSE
            ? Transaction::STATUS_PAID
            : Transaction::STATUS_PENDING;

        $transaction->update([
            'type' => $type,
            'person_id' => $request->input('personId'),
            'note' => $request->string('note')->trim()->toString() ?: null,
            'amount' => $request->input('amount'),
            'transaction_date' => $request->string('transactionDate')->toString(),
            'status' => $status,
            'received_on' => null,
            'received_note' => null,
        ]);

        $transaction->load(['person', 'settlements']);

        return response()->json([
            'transaction' => $transaction->toApiArray(),
        ]);
    }

    public function destroy(Request $request, Transaction $transaction): JsonResponse
    {
        $this->authorizeTransaction($request, $transaction);
        $transaction->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function markReceived(MarkReceivedRequest $request, Transaction $transaction): JsonResponse
    {
        $transaction->load('settlements');
        $remaining = $transaction->remainingAmount();

        if ($remaining <= 0) {
            return response()->json([
                'message' => 'Transaction is already fully received.',
            ], 422);
        }

        return $this->recordPaymentInternal(
            $request,
            $transaction,
            $remaining,
            $request->string('receivedOn')->toString(),
            $request->string('receivedNote')->trim()->toString() ?: null,
        );
    }

    public function recordPayment(RecordPaymentRequest $request, Transaction $transaction): JsonResponse
    {
        return $this->recordPaymentInternal(
            $request,
            $transaction,
            (float) $request->input('amount'),
            $request->string('receivedOn')->toString(),
            $request->string('receivedNote')->trim()->toString() ?: null,
        );
    }

    private function recordPaymentInternal(
        Request $request,
        Transaction $transaction,
        float $amount,
        string $receivedOn,
        ?string $note,
    ): JsonResponse {
        $this->authorizeTransaction($request, $transaction);

        if ($transaction->type !== Transaction::TYPE_CREDIT_GIVEN) {
            return response()->json([
                'message' => 'Only credit given transactions can receive payments.',
            ], 422);
        }

        if ($transaction->status === Transaction::STATUS_RECEIVED) {
            return response()->json([
                'message' => 'Transaction is already fully received.',
            ], 422);
        }

        $transaction->load('settlements');
        $remaining = $transaction->remainingAmount();

        if ($amount > $remaining) {
            return response()->json([
                'message' => "Payment cannot exceed remaining amount of {$remaining}.",
            ], 422);
        }

        DB::transaction(function () use ($transaction, $amount, $receivedOn, $note): void {
            $transaction->settlements()->create([
                'amount' => $amount,
                'received_on' => $receivedOn,
                'note' => $note,
            ]);

            $transaction->syncPaymentStatus();
        });

        $transaction->load(['person', 'settlements']);

        return response()->json([
            'transaction' => $transaction->fresh(['person', 'settlements'])->toApiArray(),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $monthStart = Carbon::now()->startOfMonth()->toDateString();

        $creditTransactions = Transaction::query()
            ->with('settlements')
            ->where('user_id', $userId)
            ->where('type', Transaction::TYPE_CREDIT_GIVEN)
            ->whereIn('status', [Transaction::STATUS_PENDING, Transaction::STATUS_PARTIAL])
            ->get();

        $pendingCreditTotal = $creditTransactions->sum(
            fn (Transaction $transaction) => $transaction->remainingAmount()
        );

        $receivedCreditTotal = Settlement::query()
            ->whereHas('transaction', function ($query) use ($userId): void {
                $query->where('user_id', $userId)
                    ->where('type', Transaction::TYPE_CREDIT_GIVEN);
            })
            ->whereDate('received_on', '>=', $monthStart)
            ->sum('amount');

        $expenseTotalThisMonth = Transaction::query()
            ->where('user_id', $userId)
            ->where('type', Transaction::TYPE_EXPENSE)
            ->whereDate('transaction_date', '>=', $monthStart)
            ->sum('amount');

        $expenseTotalAllTime = Transaction::query()
            ->where('user_id', $userId)
            ->where('type', Transaction::TYPE_EXPENSE)
            ->sum('amount');

        return response()->json([
            'pendingCreditTotal' => (float) $pendingCreditTotal,
            'receivedCreditTotal' => (float) $receivedCreditTotal,
            'expenseTotalThisMonth' => (float) $expenseTotalThisMonth,
            'expenseTotalAllTime' => (float) $expenseTotalAllTime,
        ]);
    }

    private function authorizeTransaction(Request $request, Transaction $transaction): void
    {
        if ($transaction->user_id !== $request->user()->id) {
            abort(404);
        }
    }
}
