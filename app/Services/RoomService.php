<?php

namespace App\Services;

use App\Interfaces\Repositories\RoomRepositoryInterface;
use App\Interfaces\Services\RoomServiceInterface;
use App\Models\Room;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RoomService implements RoomServiceInterface
{
    public function __construct(private readonly RoomRepositoryInterface $rooms) {}

    public function list(User $user): LengthAwarePaginator
    {
        return $this->rooms->paginate($user);
    }

    public function available(User $user, array $filters): LengthAwarePaginator
    {
        if (isset($filters['hotel_id'])) {
            $user->requireHotelAccess($filters['hotel_id']);
        }

        return $this->rooms->available($user, $filters);
    }

    public function show(User $user, Room $room): Room
    {
        $user->requireHotelAccess($room->hotel_id);

        return $this->rooms->details($room);
    }

    public function create(User $user, array $data): Room
    {
        $user->requireHotelAccess($data['hotel_id'], true);
        $this->checkCategory($data['room_category_id'] ?? null, $data['hotel_id']);

        return $this->rooms->create($data);
    }

    public function update(User $user, Room $room, array $data): Room
    {
        $user->requireHotelAccess($room->hotel_id, true);

        return DB::transaction(function () use ($user, $room, $data) {
            $room = $this->rooms->lock($room->id);
            $user->requireHotelAccess($room->hotel_id, true);
            if (isset($data['hotel_id'])) {
                $user->requireHotelAccess($data['hotel_id'], true);
            }
            if (isset($data['hotel_id']) && $data['hotel_id'] != $room->hotel_id && $this->rooms->hasReservations($room)) {
                throw new ConflictHttpException('Quarto com reservas não pode mudar de hotel.');
            }
            $this->checkCategory(
                array_key_exists('room_category_id', $data) ? $data['room_category_id'] : $room->room_category_id,
                $data['hotel_id'] ?? $room->hotel_id,
            );

            return $this->rooms->update($room, $data);
        });
    }

    public function delete(User $user, Room $room): void
    {
        DB::transaction(function () use ($user, $room) {
            $room = $this->rooms->lock($room->id);
            $user->requireHotelAccess($room->hotel_id, true);
            if ($this->rooms->hasReservations($room)) {
                throw new ConflictHttpException('Quarto possui reservas.');
            }
            $this->rooms->delete($room);
        });
    }

    private function checkCategory(?int $categoryId, int $hotelId): void
    {
        if ($categoryId !== null && ! $this->rooms->categoryBelongsToHotel($categoryId, $hotelId)) {
            throw ValidationException::withMessages(['room_category_id' => 'Categoria deve pertencer ao hotel do quarto.']);
        }
    }
}
