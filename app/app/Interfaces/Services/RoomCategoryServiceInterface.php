<?php

namespace App\Interfaces\Services;

use App\Models\Hotel;
use App\Models\RoomCategory;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RoomCategoryServiceInterface
{
    public function list(User $user, Hotel $hotel): LengthAwarePaginator;

    public function available(User $user, Hotel $hotel, array $period): LengthAwarePaginator;

    public function create(User $user, Hotel $hotel, array $data): RoomCategory;
}
