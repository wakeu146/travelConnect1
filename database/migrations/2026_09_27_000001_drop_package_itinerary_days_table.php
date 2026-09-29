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
        Schema::dropIfExists('package_itinerary_days');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('package_itinerary_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->unsignedInteger('day_number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->string('meals')->nullable();
            $table->string('overnight')->nullable();
            $table->timestamps();

            $table->index(['package_id', 'day_number']);
        });
    }
};
