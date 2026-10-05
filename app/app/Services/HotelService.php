<?php

namespace App\Services;

use App\Interfaces\Repositories\HotelRepositoryInterface;
use App\Interfaces\Services\HotelServiceInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class HotelService implements HotelServiceInterface
{
    public function __construct(private readonly HotelRepositoryInterface $hotels) {}

    public function list(User $user): LengthAwarePaginator
    {
        return $this->hotels->paginateForUser($user);
    }
}
