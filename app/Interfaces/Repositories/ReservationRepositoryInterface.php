<?php

namespace App\Interfaces\Repositories;

use App\Models\Coupon;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ReservationRepositoryInterface
{
    public function paginate(User $user): LengthAwarePaginator;

    public function details(Reservation $reservation): Reservation;

    public function lockCategory(int $id): RoomCategory;

    public function lockCategoryRooms(RoomCategory $category): Collection;

    public function lockRoom(int $id): Room;

    public function overlaps(int $roomId, array $period, ?int $exceptId = null): bool;

    public function lockCoupon(int $hotelId, string $code): ?Coupon;

    public function findByExternalId(int $id): ?Reservation;

    public function manualPayments(Reservation $reservation): Collection;

    public function persist(?Reservation $existing, array $attributes, array $children): Reservation;
}
