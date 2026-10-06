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
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('admin_session_version')->default(1);
        });

        Schema::create('admin_login_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email', 191)->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('outcome', 32);
            $table->string('failed_step', 32)->nullable();
            $table->char('device_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
            $table->index(['ip_address', 'created_at']);
        });

        Schema::create('trusted_admin_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->text('user_agent')->nullable();
            $table->string('first_ip', 45)->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'last_used_at']);
        });

        Schema::create('admin_login_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('session_hash', 64)->unique();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_login_challenges');
        Schema::dropIfExists('trusted_admin_devices');
        Schema::dropIfExists('admin_login_events');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('admin_session_version');
        });
    }
};
