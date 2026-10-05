<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HotelApiTest extends TestCase
{
    use RefreshDatabase;

    private function room(): Room
    {
        return Room::create(['hotel_id' => Hotel::create(['name' => 'Hotel'])->id, 'name' => 'Quarto']);
    }

    private function authorize(Room $room, string $role = 'manager'): User
    {
        $user = User::factory()->create();
        $user->hotels()->attach($room->hotel_id, ['role' => $role]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function payload(Room $room): array
    {
        return ['room_id' => $room->id, 'check_in' => '2027-01-10', 'check_out' => '2027-01-12',
            'guests' => [['name' => 'Ana', 'last_name' => 'Silva', 'phone' => '5571999999999']],
            'dailies' => [['date' => '2027-01-10', 'value' => '100.00'], ['date' => '2027-01-11', 'value' => '150.00']],
            'payments' => [['method' => 1, 'value' => '100.00']]];
    }

    public function test_api_requires_authentication(): void
    {
        $this->getJson('/api/hotels')->assertUnauthorized()->assertHeader('X-Request-ID');
        $this->get('/api/hotels')->assertUnauthorized()->assertHeader('Content-Type', 'application/json');
    }

    public function test_login_issues_token_and_logout_revokes_it(): void
    {
        $user = User::factory()->create(['password' => 'UmaSenhaSegura123']);
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'errada'])->assertUnauthorized();
        $login = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'UmaSenhaSegura123'])->assertOk()->assertJsonMissingPath('user.password');
        $headers = ['Authorization' => 'Bearer '.$login->json('access_token')];
        $this->getJson('/api/auth/me', $headers)->assertOk()->assertJsonPath('id', $user->id);
        $this->postJson('/api/auth/logout', [], $headers)->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        app('auth')->forgetGuards();
        $this->getJson('/api/auth/me', $headers)->assertUnauthorized();
    }

    public function test_login_expiry_and_logout_only_revokes_the_current_device(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['email' => 'devices@example.com', 'password' => 'UmaSenhaSegura123']);
        $data = ['email' => 'DEVICES@EXAMPLE.COM', 'password' => 'UmaSenhaSegura123'];
        $first = $this->postJson('/api/auth/login', $data + ['device_name' => 'Primeiro'])
            ->assertOk()->assertJsonPath('expires_at', now()->addHours(8)->toIso8601String())->json('access_token');
        $second = $this->postJson('/api/auth/login', $data + ['device_name' => 'Segundo'])
            ->assertOk()->json('access_token');
        $this->assertDatabaseCount('personal_access_tokens', 2);
        $firstHeaders = ['Authorization' => 'Bearer '.$first];
        $secondHeaders = ['Authorization' => 'Bearer '.$second];
        $this->postJson('/api/auth/logout', [], $firstHeaders)->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1);
        app('auth')->forgetGuards();
        $this->getJson('/api/auth/me', $firstHeaders)->assertUnauthorized();
        app('auth')->forgetGuards();
        $this->getJson('/api/auth/me', $secondHeaders)->assertOk()->assertJsonPath('id', $user->id);
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('expired', ['*'], now()->subMinute());
        $this->getJson('/api/hotels', ['Authorization' => 'Bearer '.$token->plainTextToken])->assertUnauthorized();
    }

    public function test_login_rate_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'rate@example.com', 'password' => 'wrong'])->assertUnauthorized();
        }
        $this->postJson('/api/auth/login', ['email' => 'rate@example.com', 'password' => 'wrong'])->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_unlinked_user_has_empty_lists_and_cannot_access_rooms(): void
    {
        $room = $this->room();
        Sanctum::actingAs(User::factory()->create());
        foreach (['hotels', 'rooms', 'reservations'] as $resource) {
            $this->getJson('/api/'.$resource)->assertOk()->assertJsonCount(0, 'data');
        }
        $this->getJson('/api/rooms/'.$room->id)->assertForbidden();
    }

    public function test_viewer_sees_only_linked_hotel_and_cannot_write(): void
    {
        $room = $this->room();
        $other = $this->room();
        $this->authorize($room, 'viewer');
        $this->getJson('/api/hotels')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $room->hotel_id);
        $this->getJson('/api/rooms')->assertJsonCount(1, 'data');
        $this->getJson('/api/rooms/'.$other->id)->assertForbidden();
        $this->postJson('/api/rooms', ['hotel_id' => $room->hotel_id, 'name' => 'Novo'])->assertForbidden();
        $this->patchJson('/api/rooms/'.$room->id, ['name' => 'Alterado'])->assertForbidden();
        $this->deleteJson('/api/rooms/'.$room->id)->assertForbidden();
        $this->postJson('/api/reservations', $this->payload($room))->assertForbidden();
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_manager_room_crud_and_transfer_permissions(): void
    {
        $room = $this->room();
        $other = $this->room();
        $user = $this->authorize($room);
        $id = $this->postJson('/api/rooms', ['hotel_id' => $room->hotel_id, 'name' => 'Novo'])->assertCreated()->json('id');
        $this->patchJson('/api/rooms/'.$id, ['hotel_id' => $other->hotel_id])->assertForbidden();
        $this->assertDatabaseHas('rooms', ['id' => $id, 'hotel_id' => $room->hotel_id]);
        $user->hotels()->attach($other->hotel_id, ['role' => 'manager']);
        $this->patchJson('/api/rooms/'.$id, ['hotel_id' => $other->hotel_id, 'name' => 'Transferido'])->assertOk();
        $this->getJson('/api/rooms/'.$id)->assertJsonPath('name', 'Transferido');
        $this->deleteJson('/api/rooms/'.$id)->assertOk();
        $this->getJson('/api/rooms/'.$id)->assertNotFound();
    }

    public function test_revocation_takes_effect_without_new_login(): void
    {
        $room = $this->room();
        $user = $this->authorize($room);
        $this->getJson('/api/rooms/'.$room->id)->assertOk();
        $user->hotels()->detach($room->hotel_id);
        $this->getJson('/api/rooms/'.$room->id)->assertForbidden();
    }

    public function test_reservation_total_children_and_protected_room(): void
    {
        $room = $this->room();
        $other = $this->room();
        $user = $this->authorize($room);
        $user->hotels()->attach($other->hotel_id, ['role' => 'manager']);
        $data = $this->payload($room) + ['total' => 0, 'external_id' => 99];
        $created = $this->postJson('/api/reservations', $data)->assertCreated()->assertJsonPath('total', '250.00')->assertJsonPath('external_id', null)->assertJsonCount(2, 'dailies');
        $this->assertDatabaseCount('guests', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->getJson('/api/reservations/'.$created->json('id'))->assertOk();
        $this->deleteJson('/api/rooms/'.$room->id)->assertStatus(409);
        $this->patchJson('/api/rooms/'.$room->id, ['hotel_id' => $other->hotel_id])->assertStatus(409);
    }

    public function test_overlap_enclosing_period_adjacent_dates_and_other_room(): void
    {
        $room = $this->room();
        $other = Room::create(['hotel_id' => $room->hotel_id, 'name' => 'Outro']);
        $this->authorize($room);
        $data = $this->payload($room);
        $this->postJson('/api/reservations', $data)->assertCreated();
        $this->postJson('/api/reservations', $data)->assertStatus(409);
        $enclosing = $data;
        $enclosing['check_in'] = '2027-01-09';
        $enclosing['check_out'] = '2027-01-13';
        $enclosing['dailies'] = array_map(fn ($day) => ['date' => '2027-01-'.$day, 'value' => '100.00'], ['09', '10', '11', '12']);
        $this->postJson('/api/reservations', $enclosing)->assertStatus(409);
        $adjacent = $data;
        $adjacent['check_in'] = '2027-01-12';
        $adjacent['check_out'] = '2027-01-13';
        $adjacent['dailies'] = [['date' => '2027-01-12', 'value' => '100.00']];
        unset($adjacent['payments']);
        $this->postJson('/api/reservations', $adjacent)->assertCreated();
        $data['room_id'] = $other->id;
        $this->postJson('/api/reservations', $data)->assertCreated();
        $this->assertDatabaseCount('reservations', 3);
    }

    public function test_reservations_cannot_cross_hotel_boundary(): void
    {
        $room = $this->room();
        $other = $this->room();
        $this->authorize($other);
        $id = $this->postJson('/api/reservations', $this->payload($other))->assertCreated()->json('id');
        $this->authorize($room);
        $this->getJson('/api/reservations')->assertJsonCount(0, 'data');
        $this->getJson('/api/reservations/'.$id)->assertForbidden();
        $this->postJson('/api/reservations', $this->payload($other))->assertForbidden();
    }

    public static function invalidReservations(): array
    {
        return self::invalidCases();
    }

    public function test_discount_and_service_fee_are_persisted_and_total_is_calculated(): void
    {
        $room = $this->room();
        $this->authorize($room);
        $data = $this->payload($room) + ['discount' => '30.01', 'service_fee' => '10.02', 'subtotal' => 0, 'total' => 0];
        $response = $this->postJson('/api/reservations', $data)->assertCreated()
            ->assertJsonPath('subtotal', '250.00')->assertJsonPath('discount', '30.01')
            ->assertJsonPath('service_fee', '10.02')->assertJsonPath('total', '230.01');
        $this->getJson('/api/reservations/'.$response->json('id'))->assertJsonPath('total', '230.01');
        $this->assertDatabaseHas('reservations', ['id' => $response->json('id'), 'total' => '230.01']);
    }

    public function test_full_discount_can_produce_zero_total(): void
    {
        $room = $this->room();
        $this->authorize($room);
        $data = $this->payload($room) + ['discount' => '250.00'];
        $data['payments'] = [];
        $this->postJson('/api/reservations', $data)->assertCreated()->assertJsonPath('total', '0.00');
    }

    private static function invalidCases(): array
    {
        return [
            'discount exceeds subtotal' => [['discount' => '250.01'], 'discount'],
            'negative discount' => [['discount' => '-1'], 'discount'],
            'negative fee' => [['service_fee' => '-1'], 'service_fee'],
            'fee precision' => [['service_fee' => '1.123'], 'service_fee'],
            'overpayment after discount' => [['discount' => '200'], 'payments'],
            'total overflow' => [['service_fee' => '9999999999.99'], 'service_fee'],
            'missing guest' => [['guests' => []], 'guests'],
            'invalid room' => [['room_id' => 999999], 'room_id'],
            'equal dates' => [['check_out' => '2027-01-10'], 'check_out'],
            'array check in' => [['check_in' => ['2027-01-10']], 'check_in'],
            'array check out' => [['check_out' => ['2027-01-12']], 'check_out'],
            'negative daily' => [['dailies' => [['date' => '2027-01-10', 'value' => '-1'], ['date' => '2027-01-11', 'value' => '150']]], 'dailies.0.value'],
            'precision' => [['dailies' => [['date' => '2027-01-10', 'value' => '100.123'], ['date' => '2027-01-11', 'value' => '150']]], 'dailies.0.value'],
            'missing night' => [['dailies' => [['date' => '2027-01-10', 'value' => '100']]], 'dailies'],
            'duplicate dates' => [['dailies' => [['date' => '2027-01-10', 'value' => '100'], ['date' => '2027-01-10', 'value' => '150']]], 'dailies.0.date'],
            'overpayment' => [['payments' => [['method' => 1, 'value' => '300']]], 'payments'],
        ];
    }

    #[DataProvider('invalidReservations')]
    public function test_invalid_reservation_leaves_no_partial_data(array $changes, string $field): void
    {
        $room = $this->room();
        $this->authorize($room);
        $this->postJson('/api/reservations', array_replace($this->payload($room), $changes))->assertUnprocessable()->assertJsonValidationErrors($field);
        foreach (['reservations', 'guests', 'dailies', 'payments'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }
}
