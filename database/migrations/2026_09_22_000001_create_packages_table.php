<?php

use App\Enums\PackageStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('summary');
            $table->text('description')->nullable();
            $table->string('destination');
            $table->decimal('price_from', 10, 2)->default(0);
            $table->unsignedInteger('duration_days')->default(1);
            $table->string('status')->default(PackageStatus::DRAFT->value);
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['agency_id', 'status']);
            $table->index(['destination']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
