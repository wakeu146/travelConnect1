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
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->restrictOnDelete();
            $table->foreignId('traveler_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('inquiry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->string('status')->default('open');
            $table->timestamps();
            $table->unique('inquiry_id');
            $table->index(['agency_id', 'status', 'updated_at']);
            $table->index(['traveler_id', 'status', 'updated_at']);
        });

        Schema::create('conversation_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'created_at']);
            $table->index(['sender_id', 'read_at']);
        });

        DB::table('inquiries')->orderBy('id')->chunkById(200, function ($inquiries): void {
            foreach ($inquiries as $inquiry) {
                if (DB::table('conversations')->where('inquiry_id', $inquiry->id)->exists()) {
                    continue;
                }

                $conversationId = DB::table('conversations')->insertGetId([
                    'agency_id' => $inquiry->agency_id,
                    'traveler_id' => $inquiry->user_id,
                    'inquiry_id' => $inquiry->id,
                    'subject' => $inquiry->subject,
                    'status' => $inquiry->status === 'closed' ? 'closed' : 'open',
                    'created_at' => $inquiry->created_at,
                    'updated_at' => $inquiry->updated_at,
                ]);

                DB::table('conversation_messages')->insert([
                    'conversation_id' => $conversationId,
                    'sender_id' => $inquiry->user_id,
                    'body' => $inquiry->message,
                    'read_at' => null,
                    'created_at' => $inquiry->created_at,
                    'updated_at' => $inquiry->created_at,
                ]);

                if (filled($inquiry->reply_message)) {
                    $agencyOwnerId = DB::table('agencies')->where('id', $inquiry->agency_id)->value('user_id');

                    if ($agencyOwnerId) {
                        DB::table('conversation_messages')->insert([
                            'conversation_id' => $conversationId,
                            'sender_id' => $agencyOwnerId,
                            'body' => $inquiry->reply_message,
                            'read_at' => null,
                            'created_at' => $inquiry->replied_at ?? $inquiry->updated_at,
                            'updated_at' => $inquiry->replied_at ?? $inquiry->updated_at,
                        ]);
                    }
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
    }
};
