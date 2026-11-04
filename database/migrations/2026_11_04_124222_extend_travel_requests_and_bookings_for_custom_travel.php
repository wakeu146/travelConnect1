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
        Schema::table('travel_requests', function (Blueprint $table): void {
            $table->string('reference')->nullable();
            $table->string('origin')->nullable();
            $table->string('destination_country')->nullable();
            $table->date('return_date')->nullable();
            $table->boolean('flexible_dates')->default(false);
            $table->unsignedSmallInteger('traveler_count')->default(1);
            $table->string('purpose')->nullable();
            $table->decimal('budget_min', 12, 2)->nullable();
            $table->decimal('budget_max', 12, 2)->nullable();
            $table->string('budget_currency', 3)->default('XAF');
            $table->boolean('budget_flexible')->default(false);
            $table->text('special_requirements')->nullable();
            $table->string('sharing_mode')->default('selected');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('hold_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('status')->default('open')->change();
        });

        DB::table('travel_requests')
            ->whereNull('reference')
            ->orderBy('id')
            ->chunkById(200, function ($requests): void {
                foreach ($requests as $request) {
                    DB::table('travel_requests')->where('id', $request->id)->update([
                        'reference' => 'TC-REQ-'.str_pad((string) $request->id, 8, '0', STR_PAD_LEFT),
                        'published_at' => $request->created_at,
                    ]);
                }
            });

        Schema::table('travel_requests', function (Blueprint $table): void {
            $table->unique('reference');
            $table->index(['status', 'published_at']);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropForeign(['package_id']);
            $table->dropForeign(['package_availability_id']);
            $table->unsignedBigInteger('package_id')->nullable()->change();
            $table->unsignedBigInteger('package_availability_id')->nullable()->change();
            $table->foreign('package_id')->references('id')->on('packages')->restrictOnDelete();
            $table->foreign('package_availability_id')->references('id')->on('package_availability')->restrictOnDelete();
            $table->string('booking_type')->default('package')->index();
            $table->foreignId('custom_request_id')->nullable()->unique()->constrained('travel_requests')->restrictOnDelete();
            $table->foreignId('custom_proposal_id')->nullable()->unique()->constrained('travel_request_proposals')->restrictOnDelete();
            $table->json('quote_snapshot')->nullable();
        });

        Schema::table('travel_requests', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('bookings')->whereNotNull('custom_request_id')->exists()) {
            throw new RuntimeException('Custom travel bookings exist; archive them before rolling back this migration.');
        }

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropForeign(['custom_request_id']);
            $table->dropForeign(['custom_proposal_id']);
            $table->dropForeign(['package_id']);
            $table->dropForeign(['package_availability_id']);
            $table->dropIndex(['booking_type']);
            $table->dropColumn(['booking_type', 'custom_request_id', 'custom_proposal_id', 'quote_snapshot']);
            $table->unsignedBigInteger('package_id')->nullable(false)->change();
            $table->unsignedBigInteger('package_availability_id')->nullable(false)->change();
            $table->foreign('package_id')->references('id')->on('packages')->restrictOnDelete();
            $table->foreign('package_availability_id')->references('id')->on('package_availability')->restrictOnDelete();
        });

        Schema::table('travel_requests', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->dropUnique(['reference']);
            $table->dropIndex(['status', 'published_at']);
            $table->string('status')->default('open')->change();
            $table->dropColumn([
                'reference',
                'origin',
                'destination_country',
                'return_date',
                'flexible_dates',
                'traveler_count',
                'purpose',
                'budget_min',
                'budget_max',
                'budget_currency',
                'budget_flexible',
                'special_requirements',
                'sharing_mode',
                'published_at',
                'expires_at',
                'hold_reason',
                'reviewed_at',
            ]);
        });
    }
};
