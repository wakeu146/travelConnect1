<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('destination', 191);
            $table->date('travel_date')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->text('description');
            $table->enum('status', ['open', 'closed'])->default('open')->index();
            $table->timestamps();
            $table->index(['destination', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_requests');
    }
};