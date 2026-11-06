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
            if (! Schema::hasColumn('travel_requests', 'reference')) {
                $table->string('reference')->nullable();
            }
            if (! Schema::hasColumn('travel_requests', 'origin')) {
                $table->string('origin')->nullable();
            }
            if (! Schema::hasColumn('travel_requests', 'destination_country')) {
                $table->string('destination_country')->nullable();
            }
            if (! Schema::hasColumn('travel_requests', 'return_date')) {
                $table->date('return_date')->nullable();
            }
            if (! Schema::hasColumn('travel_requests', 'flexible_dates')) {
                $table->boolean('flexible_dates')->default(false);
            }
            if (! Schema::hasColumn('travel_requests', 'traveler_count')) {
                $table->unsignedSmallInteger('traveler_count')->default(1);
            }
            if (! Schema::hasColumn('travel_requests', 'purpose')) {
                $table->string('purpose')->nullable();
            }
            if (! Schema::hasColumn('travel_requests', 'budget_min')) {
                $table->decimal('budget_min', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('travel_requests', 'budget_max')) {
                $table->decimal('budget_max', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('travel_requests', 'budget_currency')) {
                $table->string('budget_currency', 3)->default('XAF');
            }
            if (! Schema::hasColumn('travel_requests', 'budget_flexible')) {
                $table->boolean('budget_flexible')->default(false);
            }
            if (! Schema::hasColumn('travel_requests', 'special_requirements')) {
                $table->text('special_requirements')->nullable();
            }
            if (! Schema::hasColumn('travel_requests', 'sharing_mode')) {
                $table->string('sharing_mode')->default('selected');
            }
            if (! Schema::hasColumn('travel_requests', 'published_at')) {
                $table->timestamp('published_at')->nullable();
            }
            if (! Schema::hasColumn('travel_requests', 'expires_at')) {
                $table->timestamp('expires_at')->nullable();
            }
            if (! Schema::hasColumn('travel_requests', 'hold_reason')) {
                $table->text('hold_reason')->nullable();
            }
            if (! Schema::hasColumn('travel_requests', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable();
            }
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

        if (! Schema::hasIndex('travel_requests', 'travel_requests_reference_unique')) {
            Schema::table('travel_requests', function (Blueprint $table): void {
                $table->unique('reference');
            });
        }
        if (! Schema::hasIndex('travel_requests', 'travel_requests_status_published_at_index')) {
            Schema::table('travel_requests', function (Blueprint $table): void {
                $table->index(['status', 'published_at']);
            });
        }

        if ($this->hasForeignKey('bookings', 'package_id', 'packages')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->dropForeign(['package_id']);
            });
        }
        if ($this->hasForeignKey('bookings', 'package_availability_id', 'package_availability')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->dropForeign(['package_availability_id']);
            });
        }

        Schema::table('bookings', function (Blueprint $table): void {
            $table->unsignedBigInteger('package_id')->nullable()->change();
            $table->unsignedBigInteger('package_availability_id')->nullable()->change();
            if (! Schema::hasColumn('bookings', 'booking_type')) {
                $table->string('booking_type')->default('package');
            }
            if (! Schema::hasColumn('bookings', 'custom_request_id')) {
                $table->unsignedBigInteger('custom_request_id')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'custom_proposal_id')) {
                $table->unsignedBigInteger('custom_proposal_id')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'quote_snapshot')) {
                $table->json('quote_snapshot')->nullable();
            }
        });

        if (! Schema::hasIndex('bookings', 'bookings_booking_type_index')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->index('booking_type');
            });
        }
        if (! Schema::hasIndex('bookings', 'bookings_custom_request_id_unique')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->unique('custom_request_id');
            });
        }
        if (! Schema::hasIndex('bookings', 'bookings_custom_proposal_id_unique')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->unique('custom_proposal_id');
            });
        }
        if (! $this->hasForeignKey('bookings', 'package_id', 'packages')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->foreign('package_id')->references('id')->on('packages')->restrictOnDelete();
            });
        }
        if (! $this->hasForeignKey('bookings', 'package_availability_id', 'package_availability')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->foreign('package_availability_id')->references('id')->on('package_availability')->restrictOnDelete();
            });
        }
        if (! $this->hasForeignKey('bookings', 'custom_request_id', 'travel_requests')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->foreign('custom_request_id')->references('id')->on('travel_requests')->restrictOnDelete();
            });
        }
        if (! $this->hasForeignKey('bookings', 'custom_proposal_id', 'travel_request_proposals')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->foreign('custom_proposal_id')->references('id')->on('travel_request_proposals')->restrictOnDelete();
            });
        }

        if ($this->hasForeignKey('travel_requests', 'user_id', 'users')) {
            Schema::table('travel_requests', function (Blueprint $table): void {
                $table->dropForeign(['user_id']);
            });
        }
        if (! $this->hasForeignKey('travel_requests', 'user_id', 'users')) {
            Schema::table('travel_requests', function (Blueprint $table): void {
                $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            });
        }
    }

    private function hasForeignKey(string $table, string $column, string $referencedTable): bool
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if ($foreignKey['columns'] === [$column] && $foreignKey['foreign_table'] === $referencedTable) {
                return true;
            }
        }

        return false;
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
