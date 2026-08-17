<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MarkReceivedRequest;
use App\Http\Requests\Api\StoreTransactionRequest;
use App\Http\Requests\Api\UpdateTransactionRequest;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()
            ->transactions()
            ->with('person')
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

        $transaction->load('person');

        return response()->json([
            'transaction' => $transaction->toApiArray(),
        ], 201);
    }

    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        $this->authorizeTransaction($request, $transaction);
        $transaction->load('person');

        return response()->json([
            'transaction' => $transaction->toApiArray(),
        ]);
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction): JsonResponse
    {
        $this->authorizeTransaction($request, $transaction);

        if ($transaction->type === Transaction::TYPE_CREDIT_GIVEN
            && $transaction->status === Transaction::STATUS_RECEIVED) {
            return response()->json([
                'message' => 'Received credit transactions cannot be edited.',
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

        $transaction->load('person');

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
        $this->authorizeTransaction($request, $transaction);

        if ($transaction->type !== Transaction::TYPE_CREDIT_GIVEN) {
            return response()->json([
                'message' => 'Only credit given transactions can be marked received.',
            ], 422);
        }

        if ($transaction->status === Transaction::STATUS_RECEIVED) {
            return response()->json([
                'message' => 'Transaction is already marked received.',
            ], 422);
        }

        $transaction->update([
            'status' => Transaction::STATUS_RECEIVED,
            'received_on' => $request->string('receivedOn')->toString(),
            'received_note' => $request->string('receivedNote')->trim()->toString() ?: null,
        ]);

        $transaction->load('person');

        return response()->json([
            'transaction' => $transaction->toApiArray(),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $monthStart = Carbon::now()->startOfMonth()->toDateString();

        $pendingCreditTotal = Transaction::query()
            ->where('user_id', $userId)
            ->where('type', Transaction::TYPE_CREDIT_GIVEN)
            ->where('status', Transaction::STATUS_PENDING)
            ->sum('amount');

        $receivedCreditTotal = Transaction::query()
            ->where('user_id', $userId)
            ->where('type', Transaction::TYPE_CREDIT_GIVEN)
            ->where('status', Transaction::STATUS_RECEIVED)
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
