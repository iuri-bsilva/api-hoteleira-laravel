<?php

namespace App\Interfaces\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface HotelServiceInterface
{
    public function list(User $user): LengthAwarePaginator;
}
