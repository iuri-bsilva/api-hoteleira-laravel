<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->date('due_date')->nullable();
            $table->decimal('daily_interest_rate', 5, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('reservations', fn (Blueprint $table) => $table->dropColumn(['due_date', 'daily_interest_rate']));
    }
};
