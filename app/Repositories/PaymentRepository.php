<?php

namespace App\Repositories;

use App\Interfaces\Repositories\PaymentRepositoryInterface;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Database\Eloquent\Collection;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function lockRoom(int $id): Room
    {
        return Room::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function lockReservation(int $id): Reservation
    {
        return Reservation::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function findByKey(Reservation $reservation, string $key): ?Payment
    {
        return $reservation->payments()->where('idempotency_key', $key)->first();
    }

    public function listForReservation(Reservation $reservation): Collection
    {
        return $reservation->payments()->orderBy('id')->get();
    }

    public function create(Reservation $reservation, array $data): Payment
    {
        $payment = $reservation->payments()->make(['method' => $data['method'], 'value' => $data['value']]);
        $payment->idempotency_key = $data['idempotency_key'];
        $payment->recorded_at = now();
        $payment->save();

        return $payment->fresh();
    }
}
