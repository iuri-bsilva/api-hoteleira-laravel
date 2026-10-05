<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = ['code', 'type', 'amount', 'minimum_subtotal', 'valid_from', 'valid_until', 'active'];

    protected $casts = ['amount' => 'decimal:2', 'minimum_subtotal' => 'decimal:2', 'active' => 'boolean'];
}
