<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Daily extends Model
{
    public $timestamps = false;

    protected $fillable = ['date', 'value'];

    protected $casts = ['value' => 'decimal:2'];
}
