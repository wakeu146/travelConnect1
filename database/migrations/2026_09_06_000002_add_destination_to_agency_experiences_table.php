<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agency_experiences', function (Blueprint $table): void {
            $table->string('destination', 191)->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('agency_experiences', function (Blueprint $table): void {
            $table->dropColumn('destination');
        });
    }
};