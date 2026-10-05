<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->uuid('idempotency_key')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->unique(['reservation_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        // MySQL may use the composite unique index to support this foreign key.
        // Restore a separate index before removing the tracking columns.
        Schema::table('payments', fn (Blueprint $table) => $table->index('reservation_id', 'payments_reservation_id_index'));
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['reservation_id', 'idempotency_key']);
            $table->dropColumn(['idempotency_key', 'recorded_at']);
        });
    }
};
