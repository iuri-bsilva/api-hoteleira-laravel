<?php

namespace App\Interfaces\Repositories;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Database\Eloquent\Collection;

interface PaymentRepositoryInterface
{
    public function lockRoom(int $id): Room;

    public function lockReservation(int $id): Reservation;

    public function findByKey(Reservation $reservation, string $key): ?Payment;

    public function listForReservation(Reservation $reservation): Collection;

    public function create(Reservation $reservation, array $data): Payment;
}
