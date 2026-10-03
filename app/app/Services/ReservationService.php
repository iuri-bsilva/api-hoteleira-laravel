<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Room;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public function save(array $data, ?int $externalId = null): Reservation
    {
        $data = Validator::make($data, [
            'room_id' => 'required|integer|exists:rooms,id',
            'check_in' => 'required|date_format:Y-m-d', 'check_out' => 'required|date_format:Y-m-d|after:check_in',
            'guests' => 'required|array|min:1|max:50', 'guests.*.name' => 'required|string|max:255',
            'guests.*.last_name' => 'required|string|max:255', 'guests.*.phone' => 'required|string|max:30',
            'dailies' => 'required|array|min:1|max:366', 'dailies.*.date' => 'required|date_format:Y-m-d|distinct',
            'dailies.*.value' => 'required|numeric|min:0|max:9999999999.99|decimal:0,2',
            'payments' => 'sometimes|array|max:100', 'payments.*.method' => 'required|integer|min:1|max:65535',
            'payments.*.value' => 'required|numeric|min:0|max:9999999999.99|decimal:0,2',
        ])->validate();
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
        $total = array_sum(array_map(fn ($daily) => $this->cents($daily['value']), $data['dailies']));
        $paid = array_sum(array_map(fn ($payment) => $this->cents($payment['value']), $data['payments'] ?? []));
        if ($total > 999999999999 || $paid > $total) {
            throw ValidationException::withMessages(['payments' => 'Total inválido ou pagamentos maiores que a reserva.']);
        }

        return DB::transaction(function () use ($data, $externalId, $total) {
            Room::whereKey($data['room_id'])->lockForUpdate()->firstOrFail();
            $existing = $externalId === null ? null : Reservation::where('external_id', $externalId)->first();
            $overlap = Reservation::where('room_id', $data['room_id'])->where('check_in', '<', $data['check_out'])->where('check_out', '>', $data['check_in']);
            if ($existing) {
                $overlap->where('id', '!=', $existing->id);
            }
            abort_if($overlap->exists(), 409, 'Quarto indisponível para o período.');
            $reservation = $existing ?? new Reservation;
            $reservation->fill(array_intersect_key($data, array_flip(['room_id', 'check_in', 'check_out'])));
            $reservation->external_id = $externalId;
            $reservation->total = number_format($total / 100, 2, '.', '');
            $reservation->save();
            foreach (['guests', 'dailies', 'payments'] as $relation) {
                $reservation->$relation()->delete();
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
}
