<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = ['external_id', 'room_id', 'check_in', 'check_out', 'total'];

    protected $casts = ['total' => 'decimal:2'];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function guests()
    {
        return $this->hasMany(Guest::class);
    }

    public function dailies()
    {
        return $this->hasMany(Daily::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
