<?php

namespace App\Interfaces\Services;

use App\Models\Room;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RoomServiceInterface
{
    public function list(User $user): LengthAwarePaginator;

    public function available(User $user, array $filters): LengthAwarePaginator;

    public function show(User $user, Room $room): Room;

    public function create(User $user, array $data): Room;

    public function update(User $user, Room $room, array $data): Room;

    public function delete(User $user, Room $room): void;
}
