<?php

namespace App\Interfaces\Repositories;

use App\Models\Room;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RoomRepositoryInterface
{
    public function paginate(User $user): LengthAwarePaginator;

    public function available(User $user, array $filters): LengthAwarePaginator;

    public function details(Room $room): Room;

    public function create(array $data): Room;

    public function lock(int $id): Room;

    public function hasReservations(Room $room): bool;

    public function categoryBelongsToHotel(int $categoryId, int $hotelId): bool;

    public function update(Room $room, array $data): Room;

    public function delete(Room $room): void;
}
