<?php

namespace App\Services;

use App\Interfaces\Repositories\RoomCategoryRepositoryInterface;
use App\Interfaces\Services\RoomCategoryServiceInterface;
use App\Models\Hotel;
use App\Models\RoomCategory;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class RoomCategoryService implements RoomCategoryServiceInterface
{
    public function __construct(private readonly RoomCategoryRepositoryInterface $categories) {}

    public function list(User $user, Hotel $hotel): LengthAwarePaginator
    {
        $user->requireHotelAccess($hotel->id);

        return $this->categories->paginate($hotel);
    }

    public function available(User $user, Hotel $hotel, array $period): LengthAwarePaginator
    {
        $user->requireHotelAccess($hotel->id);

        return $this->categories->available($hotel, $period);
    }

    public function create(User $user, Hotel $hotel, array $data): RoomCategory
    {
        $user->requireHotelAccess($hotel->id, true);

        try {
            return $this->categories->create($hotel, $data);
        } catch (QueryException $error) {
            if ($this->categories->nameExists($hotel, $data['name'])) {
                throw ValidationException::withMessages(['name' => 'Categoria já cadastrada neste hotel.']);
            }
            throw $error;
        }
    }
}
