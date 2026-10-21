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
        Schema::create('integration_failures', function (Blueprint $table): void {
            $table->id();
            $table->string('integration', 64);
            $table->string('operation', 64);
            $table->string('safe_message', 255);
            $table->string('exception_class', 255)->nullable();
            $table->timestamps();

            $table->index(['integration', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_failures');
    }
};
