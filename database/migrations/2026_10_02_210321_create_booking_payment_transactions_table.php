<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('booking_payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('payment_reference', 40)->unique();
            $table->string('provider', 64);
            $table->string('provider_reference', 191)->nullable();
            $table->string('payment_method', 64)->nullable();
            $table->string('type', 16);
            $table->string('status', 16)->default('pending');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->text('failure_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_reference']);
            $table->index(['booking_id', 'status', 'type']);
            $table->index(['provider', 'status', 'processed_at']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_payment_transactions');
    }
};
