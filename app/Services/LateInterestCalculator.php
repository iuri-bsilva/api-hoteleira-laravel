<?php

namespace App\Services;

use App\Interfaces\Services\ReservationServiceInterface;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class LateInterestCalculator
{
    public function __construct(private readonly ReservationServiceInterface $money) {}

    public function calculate(Reservation $reservation, Collection $payments): array
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $rate = $this->money->cents($reservation->daily_interest_rate ?? 0);
        $due = $reservation->due_date ? CarbonImmutable::parse($reservation->due_date, config('app.timezone'))->startOfDay() : $today;
        // Não cobra dias anteriores à criação da reserva, mesmo com vencimento histórico.
        $created = CarbonImmutable::instance($reservation->created_at)->setTimezone(config('app.timezone'))->startOfDay();
        $cursor = $due->max($created);
        $principal = $this->money->cents($reservation->total);
        $interest = 0;
        $accrued = 0;
        $paid = 0;
        $ordered = $payments->sortBy(fn ($payment) => $payment->recorded_at?->format('Y-m-d H:i:s.u').' '.str_pad((string) $payment->id, 20, '0', STR_PAD_LEFT));
        foreach ($ordered as $payment) {
            // Pagamentos iniciais/XML sem data de registro são valores já recebidos na criação.
            $date = $payment->recorded_at
                ? CarbonImmutable::instance($payment->recorded_at)->setTimezone(config('app.timezone'))->startOfDay()->min($today)
                : $cursor;
            $days = max(0, (int) $cursor->diffInDays($date));
            $charge = $this->charge($principal, $reservation->due_date ? $rate : 0, $days);
            $interest += $charge;
            $accrued += $charge;
            $cursor = $cursor->max($date);
            $value = $this->money->cents($payment->value);
            $paid += $value;
            // Recebimentos liquidam primeiro juros vencidos, depois o principal.
            $interestPaid = min($interest, $value);
            $interest -= $interestPaid;
            $principal = max(0, $principal - ($value - $interestPaid));
        }
        $charge = $this->charge($principal, $reservation->due_date ? $rate : 0, max(0, (int) $cursor->diffInDays($today)));
        $interest += $charge;
        $accrued += $charge;
        $totalDue = $this->money->cents($reservation->total) + $accrued;
        if ($totalDue > 999999999999) {
            throw ValidationException::withMessages(['daily_interest_rate' => 'Total atualizado excede o limite permitido.']);
        }

        return ['paid' => $paid, 'principal_balance' => $principal, 'interest_total' => $accrued,
            'interest_balance' => $interest, 'total_due' => $totalDue, 'balance' => $principal + $interest,
            'overdue_days' => $reservation->due_date && $principal + $interest > 0 ? max(0, (int) $due->max($created)->diffInDays($today)) : 0];
    }

    private function charge(int $principal, int $rate, int $days): int
    {
        // Divide antes de multiplicar pelos dias para evitar overflow em períodos longos.
        $product = $principal * $rate;
        $charge = intdiv($product, 10000) * $days + intdiv(($product % 10000) * $days + 5000, 10000);
        if ($charge > 999999999999) {
            throw ValidationException::withMessages(['daily_interest_rate' => 'Juros excedem o limite permitido.']);
        }

        return $charge;
    }
}
