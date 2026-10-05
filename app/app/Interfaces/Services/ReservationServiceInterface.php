<?php

namespace App\Interfaces\Services;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ReservationServiceInterface
{
    public function save(array $data, ?int $externalId = null, ?User $user = null): Reservation;

    public function list(User $user): LengthAwarePaginator;

    public function show(User $user, Reservation $reservation): Reservation;

    public function cents(string|int|float $value): int;

    public function money(int $cents): string;
}
