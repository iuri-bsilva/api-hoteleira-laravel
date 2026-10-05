<?php

namespace App\Services;

use App\Interfaces\Repositories\ReservationRepositoryInterface;
use App\Interfaces\Services\ReservationServiceInterface;
use App\Models\Reservation;
use App\Models\User;
use App\Validation\ReservationDataValidator;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationService implements ReservationServiceInterface
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservations,
        private readonly ReservationDataValidator $validator,
    ) {}

    public function save(array $data, ?int $externalId = null, ?User $user = null): Reservation
    {
        $data = $this->validator->validate($data);
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
                $category = $this->reservations->lockCategory($data['room_category_id']);
                $user?->requireHotelAccess($category->hotel_id, true);
                $room = null;
                $candidates = $this->reservations->lockCategoryRooms($category);
                foreach ($candidates as $candidate) {
                    $occupied = $this->reservations->overlaps($candidate->id, $data);
                    if (! $occupied) {
                        $room = $candidate;
                        break;
                    }
                }
                abort_unless($room, 409, 'Categoria sem quartos disponíveis para o período.');
                $data['room_id'] = $room->id;
            } else {
                $room = $this->reservations->lockRoom($data['room_id']);
            }
            $user?->requireHotelAccess($room->hotel_id, true);
            $discount = $this->cents($data['discount'] ?? 0);
            if (isset($data['coupon_code'])) {
                $coupon = $this->reservations->lockCoupon($room->hotel_id, $data['coupon_code']);
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

            $existing = $externalId === null ? null : $this->reservations->findByExternalId($externalId);
            if ($existing) {
                $manualPaid = $this->reservations->manualPayments($existing)->sum(fn ($payment) => $this->cents($payment->value));
                if ($paid + $manualPaid > $total) {
                    throw ValidationException::withMessages(['payments' => 'Importação excede o total ao somar pagamentos registrados pela API.']);
                }
            }
            abort_if($this->reservations->overlaps($data['room_id'], $data, $existing?->id), 409, 'Quarto indisponível para o período.');

            return $this->reservations->persist($existing, [
                'room_id' => $data['room_id'], 'check_in' => $data['check_in'], 'check_out' => $data['check_out'],
                'external_id' => $externalId, 'coupon_code' => $data['coupon_code'] ?? null,
                'subtotal' => $this->money($subtotal), 'discount' => $this->money($discount),
                'service_fee' => $this->money($serviceFee), 'total' => $this->money($total),
                'due_date' => $data['due_date'] ?? null,
                'daily_interest_rate' => $data['daily_interest_rate'] ?? '0.00',
            ], $data);
        });
    }

    public function list(User $user): LengthAwarePaginator
    {
        return $this->reservations->paginate($user);
    }

    public function show(User $user, Reservation $reservation): Reservation
    {
        $user->requireHotelAccess($reservation->room->hotel_id);

        return $this->reservations->details($reservation);
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
