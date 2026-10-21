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
        Schema::table('agencies', function (Blueprint $table) {
            $table->string('operating_status')->default('active')->index();
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->timestamp('verified_at')->nullable();
        });

        Schema::create('agency_moderation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40)->index();
            $table->string('previous_verification_status')->nullable();
            $table->string('new_verification_status')->nullable();
            $table->string('previous_operating_status')->nullable();
            $table->string('new_operating_status')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agency_moderation_events');

        Schema::table('agencies', function (Blueprint $table): void {
            $table->dropIndex(['operating_status']);
            $table->dropColumn([
                'operating_status',
                'suspended_at',
                'suspension_reason',
                'verified_at',
            ]);
        });
    }
};
