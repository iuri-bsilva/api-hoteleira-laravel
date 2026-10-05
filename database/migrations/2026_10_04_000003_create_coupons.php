<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->decimal('amount', 12, 2);
            $table->decimal('minimum_subtotal', 12, 2)->default(0);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['hotel_id', 'code']);
        });
        Schema::table('reservations', fn (Blueprint $table) => $table->string('coupon_code', 50)->nullable());
    }

    public function down(): void
    {
        Schema::table('reservations', fn (Blueprint $table) => $table->dropColumn('coupon_code'));
        Schema::dropIfExists('coupons');
    }
};
