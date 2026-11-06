<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            foreach (['travel_requests', 'services'] as $table) {
                $engine = DB::selectOne(
                    'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                    [$table],
                )?->engine;

                if ($engine !== null && strcasecmp($engine, 'InnoDB') !== 0) {
                    DB::statement("ALTER TABLE `{$table}` ENGINE = InnoDB");
                }
            }
        }

        if (! Schema::hasTable('travel_request_recipients')) {
            Schema::create('travel_request_recipients', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('travel_request_id')->constrained()->restrictOnDelete();
                $table->foreignId('agency_id')->constrained()->restrictOnDelete();
                $table->timestamp('available_at');
                $table->timestamp('first_viewed_at')->nullable();
                $table->timestamp('last_viewed_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();

                $table->unique(['travel_request_id', 'agency_id']);
                $table->index(['agency_id', 'status', 'available_at']);
            });
        }

        if (! Schema::hasTable('travel_request_services')) {
            Schema::create('travel_request_services', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('travel_request_id')->constrained()->restrictOnDelete();
                $table->foreignId('service_id')->constrained()->restrictOnDelete();
                $table->timestamps();

                $table->unique(['travel_request_id', 'service_id']);
            });
        }

        if (! Schema::hasTable('travel_request_proposals')) {
            Schema::create('travel_request_proposals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('travel_request_id')->constrained()->restrictOnDelete();
                $table->foreignId('agency_id')->constrained()->restrictOnDelete();
                $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
                $table->string('reference')->unique();
                $table->string('status')->default('submitted');
                $table->decimal('price', 12, 2);
                $table->string('currency', 3);
                $table->json('price_breakdown')->nullable();
                $table->json('services_included')->nullable();
                $table->json('services_excluded')->nullable();
                $table->text('itinerary')->nullable();
                $table->string('estimated_timeline')->nullable();
                $table->text('message');
                $table->text('terms')->nullable();
                $table->date('valid_until')->nullable();
                $table->timestamp('submitted_at');
                $table->timestamp('withdrawn_at')->nullable();
                $table->timestamp('selected_at')->nullable();
                $table->timestamps();

                $table->unique(['travel_request_id', 'agency_id']);
                $table->index(['agency_id', 'status', 'submitted_at']);
            });
        }

        if (! Schema::hasTable('travel_request_revisions')) {
            Schema::create('travel_request_revisions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('travel_request_proposal_id')->constrained()->restrictOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('snapshot');
                $table->timestamps();
                $table->index(['travel_request_proposal_id', 'created_at'], 'tr_req_revisions_proposal_created_idx');
            });
        }

        if (! Schema::hasTable('travel_request_events')) {
            Schema::create('travel_request_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('travel_request_id')->constrained()->restrictOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('event_type');
                $table->json('details')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['travel_request_id', 'created_at']);
                $table->index('event_type');
            });
        }

        if (! Schema::hasTable('travel_request_admin_notes')) {
            Schema::create('travel_request_admin_notes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('travel_request_id')->constrained()->restrictOnDelete();
                $table->foreignId('admin_id')->constrained('users')->restrictOnDelete();
                $table->text('content');
                $table->timestamps();
                $table->index(['travel_request_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_request_admin_notes');
        Schema::dropIfExists('travel_request_events');
        Schema::dropIfExists('travel_request_revisions');
        Schema::dropIfExists('travel_request_proposals');
        Schema::dropIfExists('travel_request_services');
        Schema::dropIfExists('travel_request_recipients');
    }
};
