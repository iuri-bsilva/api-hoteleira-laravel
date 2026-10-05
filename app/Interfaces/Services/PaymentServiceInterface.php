<?php

namespace App\Interfaces\Services;

use App\DTOs\PaymentResult;
use App\Models\Reservation;
use App\Models\User;

interface PaymentServiceInterface
{
    public function summary(Reservation $reservation, User $user): array;

    public function record(Reservation $reservation, User $user, array $data): PaymentResult;
}
