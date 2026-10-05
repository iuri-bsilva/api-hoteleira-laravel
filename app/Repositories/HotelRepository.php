<?php

namespace App\Repositories;

use App\Interfaces\Repositories\HotelRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class HotelRepository implements HotelRepositoryInterface
{
    public function paginateForUser(User $user): LengthAwarePaginator
    {
        return $user->hotels()->paginate(20);
    }
}
