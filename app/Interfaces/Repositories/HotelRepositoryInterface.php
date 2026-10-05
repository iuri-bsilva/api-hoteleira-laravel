<?php

namespace App\Interfaces\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface HotelRepositoryInterface
{
    public function paginateForUser(User $user): LengthAwarePaginator;
}
