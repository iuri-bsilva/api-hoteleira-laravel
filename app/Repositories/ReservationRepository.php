<?php

namespace App\Repositories;

use App\Interfaces\Repositories\ReservationRepositoryInterface;
use App\Models\Coupon;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ReservationRepository implements ReservationRepositoryInterface
{
    public function paginate(User $user): LengthAwarePaginator
    {
        return Reservation::whereHas('room', fn ($query) => $query->whereIn('hotel_id', $user->hotelIds()))
            ->with('room.hotel', 'guests', 'dailies', 'payments')->paginate(20);
    }

    public function details(Reservation $reservation): Reservation
    {
        return $reservation->load('room.hotel', 'guests', 'dailies', 'payments');
    }

    public function lockCategory(int $id): RoomCategory
    {
        return RoomCategory::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function lockCategoryRooms(RoomCategory $category): Collection
    {
        return Room::where('room_category_id', $category->id)->where('hotel_id', $category->hotel_id)
            ->orderBy('id')->lockForUpdate()->get();
    }

    public function lockRoom(int $id): Room
    {
        return Room::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function overlaps(int $roomId, array $period, ?int $exceptId = null): bool
    {
        return Reservation::where('room_id', $roomId)->where('check_in', '<', $period['check_out'])
            ->where('check_out', '>', $period['check_in'])
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->lockForUpdate()->first() !== null;
    }

    public function lockCoupon(int $hotelId, string $code): ?Coupon
    {
        return Coupon::where('hotel_id', $hotelId)->where('code', $code)->lockForUpdate()->first();
    }

    public function findByExternalId(int $id): ?Reservation
    {
        return Reservation::where('external_id', $id)->first();
    }

    public function manualPayments(Reservation $reservation): Collection
    {
        return $reservation->payments()->whereNotNull('idempotency_key')->get();
    }

    public function persist(?Reservation $existing, array $attributes, array $children): Reservation
    {
        $reservation = $existing ?? new Reservation;
        $reservation->fill(array_intersect_key($attributes, array_flip(['room_id', 'check_in', 'check_out'])));
        foreach (['external_id', 'coupon_code', 'subtotal', 'discount', 'service_fee', 'total'] as $field) {
            $reservation->$field = $attributes[$field];
        }
        $reservation->save();
        foreach (['guests', 'dailies', 'payments'] as $relation) {
            $query = $reservation->$relation();
            if ($relation === 'payments') {
                $query->whereNull('idempotency_key');
            }
            $query->delete();
            $reservation->$relation()->createMany($children[$relation] ?? []);
        }

        return $this->details($reservation);
    }
}
