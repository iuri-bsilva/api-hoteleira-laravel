<?php

namespace App\Repositories;

use App\Interfaces\Repositories\RoomRepositoryInterface;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RoomRepository implements RoomRepositoryInterface
{
    public function paginate(User $user): LengthAwarePaginator
    {
        return Room::whereIn('hotel_id', $user->hotelIds())->with('hotel', 'category')->paginate(20);
    }

    public function available(User $user, array $filters): LengthAwarePaginator
    {
        return Room::whereIn('hotel_id', $user->hotelIds())
            ->when(isset($filters['hotel_id']), fn ($query) => $query->where('hotel_id', $filters['hotel_id']))
            ->whereDoesntHave('reservations', fn ($query) => $query
                ->where('check_in', '<', $filters['check_out'])
                ->where('check_out', '>', $filters['check_in']))
            ->with('hotel', 'category')->orderBy('id')->paginate(20);
    }

    public function details(Room $room): Room
    {
        return $room->load('hotel', 'category');
    }

    public function create(array $data): Room
    {
        return Room::create($data);
    }

    public function lock(int $id): Room
    {
        return Room::lockForUpdate()->findOrFail($id);
    }

    public function hasReservations(Room $room): bool
    {
        return $room->reservations()->exists();
    }

    public function categoryBelongsToHotel(int $categoryId, int $hotelId): bool
    {
        return RoomCategory::whereKey($categoryId)->where('hotel_id', $hotelId)->exists();
    }

    public function update(Room $room, array $data): Room
    {
        $room->update($data);

        return $room;
    }

    public function delete(Room $room): void
    {
        $room->delete();
    }
}
