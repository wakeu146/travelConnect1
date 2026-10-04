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
        Schema::table('reviews', function (Blueprint $table): void {
            $table->foreignId('booking_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->text('agency_response')->nullable()->after('comment');
            $table->timestamp('agency_responded_at')->nullable()->after('agency_response');
            $table->unique('booking_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropUnique(['booking_id']);
            $table->dropConstrainedForeignId('booking_id');
            $table->dropColumn(['agency_response', 'agency_responded_at']);
        });
    }
};
