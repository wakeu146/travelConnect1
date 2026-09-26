<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_availability', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->date('departure_date');
            $table->unsignedInteger('capacity')->default(1);
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['package_id', 'departure_date']);
            $table->index(['package_id', 'status']);
            $table->index('departure_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_availability');
    }
};
