<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('received_on');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['transaction_id', 'received_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
