<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    private function setupHotel(string $role = 'manager'): Room
    {
        $hotel = Hotel::create(['name' => 'Hotel']);
        $room = Room::create(['hotel_id' => $hotel->id, 'name' => 'Quarto']);
        $user = User::factory()->create();
        $user->hotels()->attach($hotel->id, ['role' => $role]);
        Sanctum::actingAs($user);

        return $room;
    }

    private function payload(Room $room): array
    {
        return ['room_id' => $room->id, 'check_in' => '2027-11-10', 'check_out' => '2027-11-11',
            'guests' => [['name' => 'Ana', 'last_name' => 'Silva', 'phone' => '5571999999999']],
            'dailies' => [['date' => '2027-11-10', 'value' => '250.00']], 'coupon_code' => 'FOCO30'];
    }

    private function coupon(Room $room, array $changes = []): Coupon
    {
        return $room->hotel->coupons()->create(array_replace(['code' => 'FOCO30', 'amount' => '30.00', 'minimum_subtotal' => '200.00', 'active' => true], $changes));
    }

    public function test_manager_creates_lists_and_deactivates_coupon(): void
    {
        $room = $this->setupHotel();
        $url = '/api/hotels/'.$room->hotel_id.'/coupons';
        $created = $this->postJson($url, ['code' => ' foco30 ', 'amount' => '30.00'])->assertCreated()->assertJsonPath('code', 'FOCO30');
        $this->postJson($url, ['code' => 'FOCO30', 'amount' => '30'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data');
        $this->patchJson($url.'/'.$created->json('id'), ['active' => false])->assertOk()->assertJsonPath('active', false);
    }

    public function test_viewer_cannot_manage_coupons(): void
    {
        $room = $this->setupHotel('viewer');
        $coupon = $this->coupon($room);
        $url = '/api/hotels/'.$room->hotel_id.'/coupons';
        $this->getJson($url)->assertForbidden();
        $this->postJson($url, ['code' => 'NOVO', 'amount' => '30'])->assertForbidden();
        $this->patchJson($url.'/'.$coupon->id, ['active' => false])->assertForbidden();
    }

    public function test_coupon_is_scoped_to_hotel_in_management(): void
    {
        $room = $this->setupHotel();
        $coupon = $this->coupon($room);
        $hotel = Hotel::create(['name' => 'Outro']);
        $this->getJson('/api/hotels/'.$hotel->id.'/coupons')->assertForbidden();
        $this->patchJson('/api/hotels/'.$hotel->id.'/coupons/'.$coupon->id, ['active' => false])->assertNotFound();
        $this->postJson('/api/hotels/'.$room->hotel_id.'/coupons', ['code' => 'DATAS', 'amount' => '30', 'valid_from' => '2027-10-10', 'valid_until' => '2027-10-09'])->assertUnprocessable()->assertJsonValidationErrors('valid_until');
    }

    public function test_coupon_total_snapshot_survives_deactivation(): void
    {
        $room = $this->setupHotel();
        $coupon = $this->coupon($room, ['valid_from' => now()->toDateString(), 'valid_until' => now()->toDateString()]);
        $data = $this->payload($room);
        $data['coupon_code'] = ' foco30 ';
        $data['service_fee'] = '10';
        $created = $this->postJson('/api/reservations', $data)->assertCreated()->assertJsonPath('discount', '30.00')->assertJsonPath('total', '230.00')->assertJsonPath('coupon_code', 'FOCO30');
        $this->patchJson('/api/hotels/'.$room->hotel_id.'/coupons/'.$coupon->id, ['active' => false])->assertOk();
        $this->getJson('/api/reservations/'.$created->json('id'))->assertJsonPath('discount', '30.00')->assertJsonPath('total', '230.00');
    }

    public function test_coupon_is_capped_at_subtotal(): void
    {
        $room = $this->setupHotel();
        $this->coupon($room, ['amount' => '300.00']);
        $this->postJson('/api/reservations', $this->payload($room))->assertCreated()->assertJsonPath('discount', '250.00')->assertJsonPath('total', '0.00');
    }

    public static function invalidCoupons(): array
    {
        return [['inactive'], ['expired'], ['future'], ['minimum'], ['other_hotel'], ['missing'], ['manual_discount'], ['overpayment']];
    }

    #[DataProvider('invalidCoupons')]
    public function test_coupon_rules_reject_without_partial_reservation(string $case): void
    {
        $room = $this->setupHotel();
        $data = $this->payload($room);
        $changes = match ($case) {
            'inactive' => ['active' => false],
            'expired' => ['valid_until' => now()->subDay()->toDateString()],
            'future' => ['valid_from' => now()->addDay()->toDateString()],
            'minimum' => ['minimum_subtotal' => '250.01'],
            default => [],
        };
        if ($case === 'other_hotel') {
            $hotel = Hotel::create(['name' => 'Outro']);
            $hotel->coupons()->create(['code' => 'FOCO30', 'amount' => '30']);
        } elseif ($case !== 'missing') {
            $this->coupon($room, $changes);
        }
        if ($case === 'manual_discount') {
            $data['discount'] = 0;
        }
        if ($case === 'overpayment') {
            $data['payments'] = [['method' => 1, 'value' => '250']];
        }
        $field = match ($case) {
            'manual_discount' => 'discount', 'overpayment' => 'payments', default => 'coupon_code'
        };
        $this->postJson('/api/reservations', $data)->assertUnprocessable()->assertJsonValidationErrors($field);
        foreach (['reservations', 'guests', 'dailies', 'payments'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }
}
