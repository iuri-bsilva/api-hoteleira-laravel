<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'last_name', 'phone'];
}
