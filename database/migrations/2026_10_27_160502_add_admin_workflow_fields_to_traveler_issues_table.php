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
        Schema::table('traveler_issues', function (Blueprint $table): void {
            $table->foreignId('opened_by_admin_id')->nullable()->after('booking_id')->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_admin_id')->nullable()->after('opened_by_admin_id')->constrained('users')->nullOnDelete();
            $table->string('category', 32)->default('general')->after('type');
            $table->string('priority', 16)->default('normal')->after('status');
            $table->text('resolution_reason')->nullable()->after('priority');
            $table->timestamp('resolved_at')->nullable()->after('resolution_reason');
            $table->index(['type', 'status', 'priority']);
            $table->index('assigned_admin_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('traveler_issues', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_admin_id');
            $table->dropConstrainedForeignId('opened_by_admin_id');
            $table->dropColumn(['category', 'priority', 'resolution_reason', 'resolved_at']);
        });
    }
};
