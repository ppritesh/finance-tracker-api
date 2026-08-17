<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PersonController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'user']);
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('summary', [TransactionController::class, 'summary']);

    Route::get('persons', [PersonController::class, 'index']);
    Route::post('persons', [PersonController::class, 'store']);
    Route::put('persons/{person}', [PersonController::class, 'update']);
    Route::delete('persons/{person}', [PersonController::class, 'destroy']);

    Route::get('transactions', [TransactionController::class, 'index']);
    Route::post('transactions', [TransactionController::class, 'store']);
    Route::get('transactions/{transaction}', [TransactionController::class, 'show']);
    Route::put('transactions/{transaction}', [TransactionController::class, 'update']);
    Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy']);
    Route::post('transactions/{transaction}/mark-received', [TransactionController::class, 'markReceived']);
});
