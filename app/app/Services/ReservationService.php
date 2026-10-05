<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public function save(array $data, ?int $externalId = null, ?User $user = null): Reservation
    {
        if (array_key_exists('room_id', $data) && array_key_exists('room_category_id', $data)) {
            throw ValidationException::withMessages(['room_category_id' => 'Informe somente room_id ou room_category_id.']);
        }
        if (isset($data['coupon_code']) && is_string($data['coupon_code'])) {
            $data['coupon_code'] = strtoupper(trim($data['coupon_code']));
        }
        $data = Validator::make($data, [
            'room_id' => 'required_without:room_category_id|integer|exists:rooms,id',
            'room_category_id' => 'required_without:room_id|integer|exists:room_categories,id',
            'check_in' => 'required|date_format:Y-m-d', 'check_out' => 'required|date_format:Y-m-d|after:check_in',
            'guests' => 'required|array|min:1|max:50', 'guests.*.name' => 'required|string|max:255',
            'guests.*.last_name' => 'required|string|max:255', 'guests.*.phone' => 'required|string|max:30',
            'dailies' => 'required|array|min:1|max:366', 'dailies.*.date' => 'required|date_format:Y-m-d|distinct',
            'dailies.*.value' => 'required|numeric|min:0|max:9999999999.99|decimal:0,2',
            'payments' => 'sometimes|array|max:100', 'payments.*.method' => 'required|integer|min:1|max:65535',
            'payments.*.value' => 'required|numeric|min:0|max:9999999999.99|decimal:0,2',
            'discount' => 'sometimes|required|numeric|min:0|max:9999999999.99|decimal:0,2',
            'service_fee' => 'sometimes|required|numeric|min:0|max:9999999999.99|decimal:0,2',
            'coupon_code' => 'sometimes|required|string|max:50|regex:/^[A-Z0-9_-]+$/',
        ])->validate();
        if (isset($data['coupon_code']) && array_key_exists('discount', $data)) {
            throw ValidationException::withMessages(['discount' => 'Não combine desconto manual com cupom.']);
        }
        $start = CarbonImmutable::parse($data['check_in']);
        $end = CarbonImmutable::parse($data['check_out']);
        $expected = [];
        for ($date = $start; $date->lt($end); $date = $date->addDay()) {
            if (count($expected) >= 366) {
                throw ValidationException::withMessages(['check_out' => 'Máximo de 366 diárias.']);
            }
            $expected[] = $date->format('Y-m-d');
        }
        $dates = array_column($data['dailies'], 'date');
        sort($dates);
        if ($dates !== $expected) {
            throw ValidationException::withMessages(['dailies' => 'Informe uma diária para cada noite, excluindo o check-out.']);
        }
        $subtotal = array_sum(array_map(fn ($daily) => $this->cents($daily['value']), $data['dailies']));
        if ($subtotal > 999999999999) {
            throw ValidationException::withMessages(['dailies' => 'Subtotal excede o limite permitido.']);
        }

        return DB::transaction(function () use ($data, $externalId, $subtotal, $user) {
            if (isset($data['room_category_id'])) {
                $category = RoomCategory::whereKey($data['room_category_id'])->lockForUpdate()->firstOrFail();
                $user?->requireHotelAccess($category->hotel_id, true);
                $room = null;
                $candidates = Room::where('room_category_id', $category->id)->where('hotel_id', $category->hotel_id)->orderBy('id')->lockForUpdate()->get();
                foreach ($candidates as $candidate) {
                    $occupied = Reservation::where('room_id', $candidate->id)->where('check_in', '<', $data['check_out'])->where('check_out', '>', $data['check_in'])->lockForUpdate()->first();
                    if (! $occupied) {
                        $room = $candidate;
                        break;
                    }
                }
                abort_unless($room, 409, 'Categoria sem quartos disponíveis para o período.');
                $data['room_id'] = $room->id;
            } else {
                $room = Room::whereKey($data['room_id'])->lockForUpdate()->firstOrFail();
            }
            $user?->requireHotelAccess($room->hotel_id, true);
            $discount = $this->cents($data['discount'] ?? 0);
            if (isset($data['coupon_code'])) {
                $coupon = Coupon::where('hotel_id', $room->hotel_id)->where('code', $data['coupon_code'])->lockForUpdate()->first();
                $today = now()->toDateString();
                if (! $coupon || ! $coupon->active || ($coupon->valid_from && $today < $coupon->valid_from) || ($coupon->valid_until && $today > $coupon->valid_until) || $subtotal < $this->cents($coupon->minimum_subtotal)) {
                    throw ValidationException::withMessages(['coupon_code' => 'Cupom indisponível ou subtotal abaixo do mínimo.']);
                }
                $discount = $coupon->type === 'percentage'
                    ? intdiv($subtotal * $this->cents($coupon->amount) + 5000, 10000)
                    : min($subtotal, $this->cents($coupon->amount));
            }
            $serviceFee = $this->cents($data['service_fee'] ?? 0);
            if ($discount > $subtotal) {
                throw ValidationException::withMessages(['discount' => 'Desconto não pode superar o subtotal das diárias.']);
            }
            $total = $subtotal - $discount + $serviceFee;
            if ($total > 999999999999) {
                throw ValidationException::withMessages(['service_fee' => 'Total com taxa excede o limite permitido.']);
            }
            $paid = array_sum(array_map(fn ($payment) => $this->cents($payment['value']), $data['payments'] ?? []));
            if ($paid > $total) {
                throw ValidationException::withMessages(['payments' => 'Total inválido ou pagamentos maiores que a reserva.']);
            }

            $existing = $externalId === null ? null : Reservation::where('external_id', $externalId)->first();
            if ($existing) {
                $manualPaid = $existing->payments()->whereNotNull('idempotency_key')->get()->sum(fn ($payment) => $this->cents($payment->value));
                if ($paid + $manualPaid > $total) {
                    throw ValidationException::withMessages(['payments' => 'Importação excede o total ao somar pagamentos registrados pela API.']);
                }
            }
            $overlap = Reservation::where('room_id', $data['room_id'])->where('check_in', '<', $data['check_out'])->where('check_out', '>', $data['check_in']);
            if ($existing) {
                $overlap->where('id', '!=', $existing->id);
            }
            abort_if($overlap->lockForUpdate()->first(), 409, 'Quarto indisponível para o período.');
            $reservation = $existing ?? new Reservation;
            $reservation->fill(array_intersect_key($data, array_flip(['room_id', 'check_in', 'check_out'])));
            $reservation->external_id = $externalId;
            $reservation->coupon_code = $data['coupon_code'] ?? null;
            $reservation->subtotal = $this->money($subtotal);
            $reservation->discount = $this->money($discount);
            $reservation->service_fee = $this->money($serviceFee);
            $reservation->total = $this->money($total);
            $reservation->save();
            foreach (['guests', 'dailies', 'payments'] as $relation) {
                $children = $reservation->$relation();
                if ($relation === 'payments') {
                    $children->whereNull('idempotency_key');
                }
                $children->delete();
                $reservation->$relation()->createMany($data[$relation] ?? []);
            }

            return $reservation->load('room.hotel', 'guests', 'dailies', 'payments');
        });
    }

    public function cents(string|int|float $value): int
    {
        $parts = explode('.', (string) $value);

        return ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    public function money(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
