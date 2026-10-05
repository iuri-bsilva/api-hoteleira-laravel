<?php

namespace App\Interfaces\Repositories;

use App\Models\Hotel;
use App\Models\RoomCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RoomCategoryRepositoryInterface
{
    public function paginate(Hotel $hotel): LengthAwarePaginator;

    public function available(Hotel $hotel, array $period): LengthAwarePaginator;

    public function create(Hotel $hotel, array $data): RoomCategory;

    public function nameExists(Hotel $hotel, string $name): bool;
}
