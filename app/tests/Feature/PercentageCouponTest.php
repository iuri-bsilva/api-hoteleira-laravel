<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PercentageCouponTest extends TestCase
{
    use RefreshDatabase;

    private function room(): Room
    {
        $hotel = Hotel::create(['name' => 'Hotel']);
        $user = User::factory()->create();
        $user->hotels()->attach($hotel->id, ['role' => 'manager']);
        Sanctum::actingAs($user);

        return Room::create(['hotel_id' => $hotel->id, 'name' => 'Quarto']);
    }

    public static function discounts(): array
    {
        return [
            ['10.00', '250.00', '25.00', '235.00'],
            ['12.50', '100.00', '12.50', '97.50'],
            ['50.00', '0.01', '0.01', '10.00'],
            ['33.33', '0.01', '0.00', '10.01'],
            ['100.00', '250.00', '250.00', '10.00'],
            ['0.01', '100.00', '0.01', '109.99'],
            ['99.99', '9999999999.99', '9998999999.99', '1000010.00'],
        ];
    }

    #[DataProvider('discounts')]
    public function test_percentages_use_subtotal_and_round_to_cents(string $percent, string $subtotal, string $discount, string $total): void
    {
        $room = $this->room();
        $this->postJson('/api/hotels/'.$room->hotel_id.'/coupons', ['code' => 'PERCENT', 'type' => 'percentage', 'amount' => $percent])
            ->assertCreated()->assertJsonPath('type', 'percentage');
        $reservation = $this->postJson('/api/reservations', [
            'room_id' => $room->id, 'check_in' => '2028-03-10', 'check_out' => '2028-03-11',
            'guests' => [['name' => 'Ana', 'last_name' => 'Silva', 'phone' => '123']],
            'dailies' => [['date' => '2028-03-10', 'value' => $subtotal]],
            'coupon_code' => 'PERCENT', 'service_fee' => '10.00',
        ])->assertCreated()->assertJsonPath('discount', $discount)->assertJsonPath('total', $total);
        $room->hotel->coupons()->where('code', 'PERCENT')->update(['active' => false]);
        $this->getJson('/api/reservations/'.$reservation->json('id'))->assertOk()->assertJsonPath('discount', $discount);
    }

    public function test_fixed_default_and_invalid_types_and_percentages(): void
    {
        $room = $this->room();
        $url = '/api/hotels/'.$room->hotel_id.'/coupons';
        $this->postJson($url, ['code' => 'FIXO', 'amount' => '300.00'])->assertCreated()->assertJsonPath('type', 'fixed');
        foreach (['100.01', '0', '-1', '1.001', '9999999999.99'] as $amount) {
            $this->postJson($url, ['code' => 'INVALIDO', 'type' => 'percentage', 'amount' => $amount])->assertUnprocessable()->assertJsonValidationErrors('amount');
        }
        $this->postJson($url, ['code' => 'INVALIDO', 'type' => 'other', 'amount' => '10'])->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->assertDatabaseCount('coupons', 1);
    }
}
