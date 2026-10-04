<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('bookings')->where('currency', '!=', 'XAF')->update(['currency' => 'XAF']);
        DB::table('booking_payment_transactions')->where('currency', '!=', 'XAF')->update(['currency' => 'XAF']);
    }

    public function down(): void {}
};
