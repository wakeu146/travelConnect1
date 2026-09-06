<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table): void {
            $table->string('logo_path')->nullable()->after('website');
            $table->string('cover_image_path')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table): void {
            $table->dropColumn(['logo_path', 'cover_image_path']);
        });
    }
};