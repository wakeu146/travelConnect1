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
        Schema::create('admin_alert_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_alert_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 24);
            $table->string('status_before', 24);
            $table->string('status_after', 24);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['admin_alert_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_alert_actions');
    }
};
