<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('service_fee', 12, 2)->default(0);
        });
        DB::table('reservations')->update(['subtotal' => DB::raw('total')]);
    }

    public function down(): void
    {
        Schema::table('reservations', fn (Blueprint $table) => $table->dropColumn(['subtotal', 'discount', 'service_fee']));
    }
};
