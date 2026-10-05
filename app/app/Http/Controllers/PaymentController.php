<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Room;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function index(Request $request, Reservation $reservation, ReservationService $money)
    {
        $request->user()->requireHotelAccess($reservation->room->hotel_id);

        return DB::transaction(function () use ($reservation, $money) {
            // A consistent financial snapshot while another request records a payment.
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            return $this->summary($reservation, $money);
        });
    }

    public function store(Request $request, Reservation $reservation, ReservationService $money)
    {
        $request->user()->requireHotelAccess($reservation->room->hotel_id, true);
        $data = $request->validate([
            'method' => 'required|integer|min:1|max:65535',
            'value' => 'required|numeric|min:0.01|max:9999999999.99|decimal:0,2',
            'idempotency_key' => 'required|string|uuid',
        ]);
        $data['idempotency_key'] = strtolower($data['idempotency_key']);

        return DB::transaction(function () use ($request, $reservation, $money, $data) {
            $room = Room::whereKey($reservation->room_id)->lockForUpdate()->firstOrFail();
            $request->user()->requireHotelAccess($room->hotel_id, true);
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $existing = $reservation->payments()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                abort_if($existing->method != $data['method'] || $money->cents($existing->value) !== $money->cents($data['value']), 409, 'Chave já utilizada com outro pagamento.');

                return response()->json(['payment' => $existing, 'summary' => $this->summary($reservation, $money)], 200);
            }
            $paid = $reservation->payments()->get()->sum(fn ($payment) => $money->cents($payment->value));
            if ($paid + $money->cents($data['value']) > $money->cents($reservation->total)) {
                throw ValidationException::withMessages(['value' => 'Pagamento supera o saldo pendente da reserva.']);
            }
            $payment = $reservation->payments()->make(['method' => $data['method'], 'value' => $data['value']]);
            $payment->idempotency_key = $data['idempotency_key'];
            $payment->recorded_at = now();
            $payment->save();

            return response()->json(['payment' => $payment->fresh(), 'summary' => $this->summary($reservation, $money)], 201);
        });
    }

    private function summary(Reservation $reservation, ReservationService $money): array
    {
        $payments = $reservation->payments()->orderBy('id')->get();
        $paid = $payments->sum(fn ($payment) => $money->cents($payment->value));
        $balance = $money->cents($reservation->total) - $paid;

        return ['reservation_id' => $reservation->id, 'total' => $reservation->total,
            'paid' => $money->money($paid), 'balance' => $money->money($balance),
            'status' => $balance === 0 ? 'paid' : ($paid === 0 ? 'unpaid' : 'partial'), 'payments' => $payments];
    }
}
