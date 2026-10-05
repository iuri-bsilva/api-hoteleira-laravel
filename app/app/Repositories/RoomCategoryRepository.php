<?php

namespace App\Repositories;

use App\Interfaces\Repositories\RoomCategoryRepositoryInterface;
use App\Models\Hotel;
use App\Models\RoomCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RoomCategoryRepository implements RoomCategoryRepositoryInterface
{
    public function paginate(Hotel $hotel): LengthAwarePaginator
    {
        return RoomCategory::where('hotel_id', $hotel->id)->withCount('rooms')->orderBy('id')->paginate(20);
    }

    public function available(Hotel $hotel, array $period): LengthAwarePaginator
    {
        return RoomCategory::where('hotel_id', $hotel->id)->withCount('rooms')
            ->withCount(['rooms as available_count' => fn ($rooms) => $rooms->whereDoesntHave('reservations', fn ($reservations) => $reservations
                ->where('check_in', '<', $period['check_out'])->where('check_out', '>', $period['check_in']))])
            ->orderBy('id')->paginate(20);
    }

    public function create(Hotel $hotel, array $data): RoomCategory
    {
        $category = new RoomCategory($data);
        $category->hotel_id = $hotel->id;
        $category->save();

        return $category->fresh();
    }

    public function nameExists(Hotel $hotel, string $name): bool
    {
        return RoomCategory::where('hotel_id', $hotel->id)->where('name', $name)->exists();
    }
}
