<?php

namespace App\Services;

use App\DTOs\PaymentResult;
use App\Interfaces\Repositories\PaymentRepositoryInterface;
use App\Interfaces\Services\PaymentServiceInterface;
use App\Interfaces\Services\ReservationServiceInterface;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PaymentService implements PaymentServiceInterface
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly ReservationServiceInterface $money,
    ) {}

    public function summary(Reservation $reservation, User $user): array
    {
        $user->requireHotelAccess($reservation->room->hotel_id);

        return DB::transaction(function () use ($reservation) {
            // Mantém uma visão financeira consistente durante pagamentos concorrentes.
            return $this->buildSummary($this->payments->lockReservation($reservation->id));
        });
    }

    public function record(Reservation $reservation, User $user, array $data): PaymentResult
    {
        $user->requireHotelAccess($reservation->room->hotel_id, true);
        $data['idempotency_key'] = strtolower($data['idempotency_key']);

        return DB::transaction(function () use ($reservation, $user, $data) {
            // A ordem quarto → reserva é compartilhada com a importação XML.
            $room = $this->payments->lockRoom($reservation->room_id);
            $user->requireHotelAccess($room->hotel_id, true);
            $reservation = $this->payments->lockReservation($reservation->id);
            $existing = $this->payments->findByKey($reservation, $data['idempotency_key']);
            if ($existing) {
                if ($existing->method != $data['method'] || $this->money->cents($existing->value) !== $this->money->cents($data['value'])) {
                    throw new ConflictHttpException('Chave já utilizada com outro pagamento.');
                }

                return new PaymentResult($existing, $this->buildSummary($reservation), false);
            }

            $paid = $this->payments->listForReservation($reservation)->sum(fn ($payment) => $this->money->cents($payment->value));
            if ($paid + $this->money->cents($data['value']) > $this->money->cents($reservation->total)) {
                throw ValidationException::withMessages(['value' => 'Pagamento supera o saldo pendente da reserva.']);
            }
            $payment = $this->payments->create($reservation, $data);

            return new PaymentResult($payment, $this->buildSummary($reservation), true);
        });
    }

    private function buildSummary(Reservation $reservation): array
    {
        $payments = $this->payments->listForReservation($reservation);
        $paid = $payments->sum(fn ($payment) => $this->money->cents($payment->value));
        $balance = $this->money->cents($reservation->total) - $paid;

        return ['reservation_id' => $reservation->id, 'total' => $reservation->total,
            'paid' => $this->money->money($paid), 'balance' => $this->money->money($balance),
            'status' => $balance === 0 ? 'paid' : ($paid === 0 ? 'unpaid' : 'partial'), 'payments' => $payments];
    }
}
