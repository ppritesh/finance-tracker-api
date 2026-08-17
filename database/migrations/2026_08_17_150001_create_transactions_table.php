<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->string('type');
            $table->text('note')->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('transaction_date');
            $table->string('status');
            $table->date('received_on')->nullable();
            $table->text('received_note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
