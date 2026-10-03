<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    public $timestamps = false;

    protected $fillable = ['method', 'value'];

    protected $casts = ['value' => 'decimal:2'];
}
