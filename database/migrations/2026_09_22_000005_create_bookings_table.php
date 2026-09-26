<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->restrictOnDelete();
            $table->foreignId('package_id')->constrained()->restrictOnDelete();
            $table->foreignId('package_availability_id')->constrained('package_availability')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('booking_reference')->unique();
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('unpaid');
            $table->unsignedInteger('traveler_count')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->text('notes')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'status']);
            $table->index(['package_id', 'status']);
            $table->index(['package_availability_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('booking_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
