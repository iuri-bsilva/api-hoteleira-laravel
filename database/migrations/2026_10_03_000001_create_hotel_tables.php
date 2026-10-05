<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_id')->nullable()->unique();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_id')->nullable()->unique();
            $table->foreignId('hotel_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_id')->nullable()->unique();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->date('check_in');
            $table->date('check_out');
            $table->decimal('total', 12, 2);
            $table->timestamps();
            $table->index(['room_id', 'check_in', 'check_out']);
        });
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('last_name');
            $table->string('phone', 30);
        });
        Schema::create('dailies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('value', 12, 2);
            $table->unique(['reservation_id', 'date']);
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('method');
            $table->decimal('value', 12, 2);
        });
    }

    public function down(): void
    {
        foreach (['payments', 'dailies', 'guests', 'reservations', 'rooms', 'hotels'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
