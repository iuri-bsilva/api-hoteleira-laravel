<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function reservation(string $role = 'manager', ?int $externalId = null): Reservation
    {
        $hotel = Hotel::create(['name' => 'Hotel']);
        $room = Room::create(['hotel_id' => $hotel->id, 'name' => 'Quarto']);
        $user = User::factory()->create();
        $user->hotels()->attach($hotel->id, ['role' => $role]);
        Sanctum::actingAs($user);

        return app(ReservationService::class)->save($this->payload($room->id), $externalId);
    }

    private function payload(int $roomId): array
    {
        return ['room_id' => $roomId, 'check_in' => '2027-12-10', 'check_out' => '2027-12-11',
            'guests' => [['name' => 'Ana', 'last_name' => 'Silva', 'phone' => '123']],
            'dailies' => [['date' => '2027-12-10', 'value' => '250.00']],
            'discount' => '30.00', 'service_fee' => '10.00', 'payments' => [['method' => 1, 'value' => '100.00']]];
    }

    private function payment(array $changes = []): array
    {
        return array_replace(['method' => 1, 'value' => '30.00', 'idempotency_key' => '123e4567-e89b-42d3-a456-426614174000'], $changes);
    }

    public function test_partial_full_and_idempotent_payments(): void
    {
        $reservation = $this->reservation();
        $url = '/api/reservations/'.$reservation->id.'/payments';
        $this->getJson($url)->assertOk()->assertJsonPath('paid', '100.00')->assertJsonPath('balance', '130.00')->assertJsonPath('status', 'partial');
        $created = $this->postJson($url, $this->payment())->assertCreated()->assertJsonPath('summary.paid', '130.00')->assertJsonPath('summary.balance', '100.00');
        $this->postJson($url, $this->payment(['value' => '30']))->assertOk()->assertJsonPath('payment.id', $created->json('payment.id'));
        $this->postJson($url, $this->payment(['value' => '31']))->assertConflict();
        $this->postJson($url, $this->payment(['method' => 2]))->assertConflict();
        $this->postJson($url, $this->payment(['value' => '100', 'idempotency_key' => '123e4567-e89b-42d3-a456-426614174001']))
            ->assertCreated()->assertJsonPath('summary.paid', '230.00')->assertJsonPath('summary.balance', '0.00')->assertJsonPath('summary.status', 'paid');
        $this->postJson($url, $this->payment(['value' => '0.01', 'idempotency_key' => '123e4567-e89b-42d3-a456-426614174002']))->assertUnprocessable();
        $this->assertDatabaseCount('payments', 3);
        $this->getJson('/api/reservations/'.$reservation->id)->assertOk()->assertJsonCount(3, 'payments')->assertJsonPath('total', '230.00');
    }

    public function test_permissions_and_missing_reservation(): void
    {
        $reservation = $this->reservation('viewer');
        $url = '/api/reservations/'.$reservation->id.'/payments';
        $this->getJson($url)->assertOk();
        $this->postJson($url, $this->payment())->assertForbidden();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson($url)->assertForbidden();
        $this->postJson($url, $this->payment())->assertForbidden();
        $this->getJson('/api/reservations/999999/payments')->assertNotFound();
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_no_token_is_rejected(): void
    {
        $this->getJson('/api/reservations/1/payments')->assertUnauthorized();
        $this->postJson('/api/reservations/1/payments', $this->payment())->assertUnauthorized();
    }

    public static function invalidPayments(): array
    {
        return [[['value' => '0']], [['value' => '-1']], [['value' => '1.001']], [['value' => '130.01']], [['method' => 0]], [['method' => 65536]], [['idempotency_key' => 'invalid']], [['idempotency_key' => null]], [['value' => '10000000000']]];
    }

    #[DataProvider('invalidPayments')]
    public function test_invalid_payments_do_not_write(array $changes): void
    {
        $reservation = $this->reservation();
        $this->postJson('/api/reservations/'.$reservation->id.'/payments', $this->payment($changes))->assertUnprocessable();
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_import_preserves_api_payments_and_their_keys(): void
    {
        $reservation = $this->reservation(externalId: 10);
        $url = '/api/reservations/'.$reservation->id.'/payments';
        $payment = $this->postJson($url, $this->payment())->assertCreated()->json('payment.id');
        for ($i = 0; $i < 2; $i++) {
            app(ReservationService::class)->save($this->payload($reservation->room_id), 10);
            $this->getJson($url)->assertOk()->assertJsonPath('paid', '130.00')->assertJsonCount(2, 'payments');
        }
        $this->postJson($url, $this->payment())->assertOk()->assertJsonPath('payment.id', $payment);
        $data = $this->payload($reservation->room_id);
        $data['payments'][0]['value'] = '230.00';
        try {
            app(ReservationService::class)->save($data, 10);
            $this->fail('Importação deveria rejeitar pagamentos maiores que o total.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('payments', $error->errors());
        }
        $this->getJson($url)->assertOk()->assertJsonPath('paid', '130.00')->assertJsonCount(2, 'payments');
    }

    public function test_zero_total_is_paid_without_payments(): void
    {
        $reservation = $this->reservation();
        $reservation->payments()->delete();
        $reservation->update(['total' => '0']);
        $this->getJson('/api/reservations/'.$reservation->id.'/payments')->assertOk()->assertJsonPath('status', 'paid')->assertJsonPath('balance', '0.00');
        $reservation->update(['total' => '230']);
        $this->getJson('/api/reservations/'.$reservation->id.'/payments')->assertOk()->assertJsonPath('status', 'unpaid');
    }
}
