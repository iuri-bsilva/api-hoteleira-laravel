<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\Room;
use App\Services\LateInterestCalculator;
use App\Services\ReservationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LateInterestExampleSeeder extends Seeder
{
    public function run(): void
    {
        $hotel = Hotel::orderBy('id')->firstOrFail();
        $today = now()->startOfDay();
        $due = $today->copy()->subDays(5);
        $examples = [
            'Sem pagamento' => [],
            'Pagamento inicial' => [['method' => 1, 'value' => '40.00']],
            'Pagamento parcial após atraso' => [],
            'Quitada com juros' => [],
        ];

        foreach ($examples as $label => $initialPayments) {
            $reservation = DB::transaction(function () use ($hotel, $today, $due, $label, $initialPayments) {
                $room = Room::firstOrCreate(['hotel_id' => $hotel->id, 'name' => 'DEMO JUROS - '.$label]);
                // Reexecutar não altera exemplos já usados nos testes manuais.
                if ($existing = $room->reservations()->first()) {
                    return $existing;
                }
                $reservation = app(ReservationService::class)->save([
                    'room_id' => $room->id,
                    'check_in' => $today->copy()->addDays(30)->toDateString(),
                    'check_out' => $today->copy()->addDays(31)->toDateString(),
                    'guests' => [['name' => 'Exemplo', 'last_name' => $label, 'phone' => '000000000']],
                    'dailies' => [['date' => $today->copy()->addDays(30)->toDateString(), 'value' => '100.00']],
                    'due_date' => $due->toDateString(), 'daily_interest_rate' => '1.00',
                    'payments' => $initialPayments,
                ]);
                // Histórico fictício explícito para demonstrar juros imediatamente.
                $reservation->forceFill(['created_at' => $due])->save();
                if ($label === 'Pagamento parcial após atraso') {
                    $payment = $reservation->payments()->make(['method' => 1, 'value' => '52.00']);
                    $payment->recorded_at = $due->copy()->addDays(2);
                    $payment->save();
                } elseif ($label === 'Quitada com juros') {
                    $payment = $reservation->payments()->make(['method' => 1, 'value' => '105.00']);
                    $payment->recorded_at = $today;
                    $payment->save();
                }

                return $reservation;
            });
            $summary = app(LateInterestCalculator::class)->calculate($reservation, $reservation->payments()->get());
            $money = app(ReservationService::class);
            $this->command?->info($label.' | hotel '.$hotel->id.' | reserva '.$reservation->id.' | juros '.$money->money($summary['interest_total']).' | saldo '.$money->money($summary['balance']));
        }
    }
}
