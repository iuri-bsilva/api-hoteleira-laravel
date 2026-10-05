<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LateInterestTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        $this->travelTo(now()->setDate(2027, 12, 1)->startOfDay());
        $hotel = Hotel::create(['name' => 'Hotel']);
        $room = Room::create(['hotel_id' => $hotel->id, 'name' => 'Quarto']);
        $user = User::factory()->create();
        $user->hotels()->attach($hotel->id, ['role' => 'manager']);
        Sanctum::actingAs($user);

        return ['room_id' => $room->id, 'check_in' => '2027-12-10', 'check_out' => '2027-12-11',
            'guests' => [['name' => 'Ana', 'last_name' => 'Silva', 'phone' => '123']],
            'dailies' => [['date' => '2027-12-10', 'value' => '100.00']],
            'due_date' => '2027-12-01', 'daily_interest_rate' => '1.00'];
    }

    public function test_daily_interest_partial_payment_and_settlement(): void
    {
        $id = $this->postJson('/api/reservations', $this->payload())->assertCreated()->json('id');
        $url = "/api/reservations/$id/payments";
        $this->getJson($url)->assertOk()->assertJsonPath('interest_total', '0.00');
        $this->travel(2)->days();
        $this->getJson($url)->assertOk()->assertJsonPath('balance', '102.00')->assertJsonPath('overdue_days', 2);
        $payment = ['method' => 1, 'value' => '52.00', 'idempotency_key' => '123e4567-e89b-42d3-a456-426614174000'];
        $this->postJson($url, $payment)->assertCreated()->assertJsonPath('summary.principal_balance', '50.00');
        $this->travel(2)->days();
        $this->postJson($url, $payment)->assertOk()->assertJsonPath('summary.balance', '51.00')->assertJsonPath('summary.interest_total', '3.00');
        $this->assertDatabaseCount('payments', 1);
        $this->postJson($url, array_replace($payment, ['value' => '51.01', 'idempotency_key' => '123e4567-e89b-42d3-a456-426614174001']))->assertUnprocessable();
        $this->postJson($url, array_replace($payment, ['value' => '51.00', 'idempotency_key' => '123e4567-e89b-42d3-a456-426614174001']))->assertCreated()->assertJsonPath('summary.status', 'paid');
        $this->travel(10)->days();
        $this->getJson($url)->assertOk()->assertJsonPath('balance', '0.00')->assertJsonPath('total', '100.00')->assertJsonPath('total_due', '103.00');
    }

    public function test_initial_payment_reduces_interest_base(): void
    {
        $data = $this->payload();
        $data['payments'] = [['method' => 1, 'value' => '40.00']];
        $id = $this->postJson('/api/reservations', $data)->assertCreated()->json('id');
        $this->travel(3)->days();
        $this->getJson("/api/reservations/$id/payments")->assertOk()->assertJsonPath('interest_total', '1.80')->assertJsonPath('balance', '61.80');
    }

    public function test_historical_due_date_does_not_charge_before_creation(): void
    {
        $data = $this->payload();
        $data['due_date'] = '2027-11-01';
        $id = $this->postJson('/api/reservations', $data)->assertCreated()->json('id');
        $this->getJson("/api/reservations/$id/payments")->assertOk()->assertJsonPath('interest_total', '0.00');
    }

    public function test_rate_and_due_date_must_be_provided_together(): void
    {
        $data = $this->payload();
        unset($data['due_date']);
        $this->postJson('/api/reservations', $data)->assertUnprocessable()->assertJsonValidationErrors('due_date');
        $data['due_date'] = '2027-12-01';
        foreach (['0', '100.01', '0.001', '-1'] as $rate) {
            $data['daily_interest_rate'] = $rate;
            $this->postJson('/api/reservations', $data)->assertUnprocessable()->assertJsonValidationErrors('daily_interest_rate');
        }
        unset($data['daily_interest_rate']);
        $this->postJson('/api/reservations', $data)->assertUnprocessable()->assertJsonValidationErrors('daily_interest_rate');
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_future_due_date_and_payment_before_due_date(): void
    {
        $data = $this->payload();
        $data['due_date'] = '2027-12-05';
        $id = $this->postJson('/api/reservations', $data)->assertCreated()->json('id');
        $url = "/api/reservations/$id/payments";
        $this->travel(2)->days();
        $this->postJson($url, ['method' => 1, 'value' => '40.00', 'idempotency_key' => '123e4567-e89b-42d3-a456-426614174000'])
            ->assertCreated()->assertJsonPath('summary.interest_total', '0.00');
        $this->travel(4)->days();
        $this->getJson($url)->assertOk()->assertJsonPath('interest_total', '1.20')->assertJsonPath('balance', '61.20');
    }

    public function test_payment_below_interest_does_not_reduce_principal_or_compound(): void
    {
        $id = $this->postJson('/api/reservations', $this->payload())->assertCreated()->json('id');
        $url = "/api/reservations/$id/payments";
        $this->travel(2)->days();
        $this->postJson($url, ['method' => 1, 'value' => '1.00', 'idempotency_key' => '123e4567-e89b-42d3-a456-426614174000'])
            ->assertCreated()->assertJsonPath('summary.principal_balance', '100.00')->assertJsonPath('summary.interest_balance', '1.00');
        $this->travel(2)->days();
        $this->getJson($url)->assertOk()->assertJsonPath('interest_total', '4.00')->assertJsonPath('balance', '103.00');
    }

    public function test_cent_rounding_and_total_limit(): void
    {
        $data = $this->payload();
        $data['dailies'][0]['value'] = '0.50';
        $id = $this->postJson('/api/reservations', $data)->assertCreated()->json('id');
        $this->travel(1)->days();
        $this->getJson("/api/reservations/$id/payments")->assertOk()->assertJsonPath('interest_total', '0.01');
        $reservation = Reservation::findOrFail($id);
        $reservation->forceFill(['total' => '9999999999.99', 'daily_interest_rate' => '100.00'])->save();
        $this->getJson("/api/reservations/$id/payments")->assertUnprocessable()->assertJsonValidationErrors('daily_interest_rate');
        $this->assertDatabaseCount('payments', 0);
    }
}
