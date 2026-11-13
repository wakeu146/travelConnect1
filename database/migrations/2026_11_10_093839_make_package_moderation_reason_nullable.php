<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('package_moderation_events', function (Blueprint $table): void {
            $table->text('reason')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('package_moderation_events')->whereNull('reason')->update(['reason' => '']);

        Schema::table('package_moderation_events', function (Blueprint $table): void {
            $table->text('reason')->nullable(false)->change();
        });
    }
};
