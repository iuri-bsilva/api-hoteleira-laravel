<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function hotel(): Hotel
    {
        $hotel = Hotel::create(['name' => 'Hotel']);
        $user = User::factory()->create();
        $user->hotels()->attach($hotel->id, ['role' => 'viewer']);
        Sanctum::actingAs($user);

        return $hotel;
    }

    public function test_only_free_authorized_rooms_are_returned_with_boundary_dates(): void
    {
        $hotel = $this->hotel();
        $room = Room::create(['hotel_id' => $hotel->id, 'name' => 'Ocupado']);
        $free = Room::create(['hotel_id' => $hotel->id, 'name' => 'Livre']);
        $other = Hotel::create(['name' => 'Outro']);
        Room::create(['hotel_id' => $other->id, 'name' => 'Oculto']);
        Reservation::create(['room_id' => $room->id, 'check_in' => '2027-10-10', 'check_out' => '2027-10-12', 'total' => '200']);
        foreach ([['2027-10-10', '2027-10-12'], ['2027-10-09', '2027-10-11'], ['2027-10-11', '2027-10-13'], ['2027-10-09', '2027-10-13']] as [$start, $end]) {
            $this->getJson('/api/rooms/availability?check_in='.$start.'&check_out='.$end)
                ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $free->id);
        }
        foreach ([['2027-10-09', '2027-10-10'], ['2027-10-12', '2027-10-13']] as [$start, $end]) {
            $this->getJson('/api/rooms/availability?check_in='.$start.'&check_out='.$end)->assertOk()->assertJsonPath('total', 2);
        }
        $this->getJson('/api/rooms/availability?check_in=2027-10-10&check_out=2027-10-12&hotel_id='.$other->id)->assertForbidden();
        $this->getJson('/api/rooms/availability?check_in=2027-10-10&check_out=2027-10-12&hotel_id='.$hotel->id)->assertOk()->assertJsonPath('total', 1);
    }

    public static function invalidDates(): array
    {
        return [[[]], [['check_in' => 'invalid', 'check_out' => '2027-01-01']], [['check_in' => '2027-01-01', 'check_out' => '2027-01-01']], [['check_in' => '2027-01-02', 'check_out' => '2027-01-01']], [['check_in' => '2027-01-01', 'check_out' => '2028-01-03']], [['check_in' => ['invalid'], 'check_out' => '2027-01-01']]];
    }

    #[DataProvider('invalidDates')]
    public function test_invalid_dates_return_json_validation(array $params): void
    {
        $this->hotel();
        $this->getJson('/api/rooms/availability?'.http_build_query($params))->assertUnprocessable();
    }

    public function test_authentication_and_empty_access(): void
    {
        $url = '/api/rooms/availability?check_in=2027-01-01&check_out=2027-01-02';
        $this->getJson($url)->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson($url)->assertOk()->assertJsonPath('total', 0);
    }

    public function test_pagination_preserves_filters_and_366_nights_are_allowed(): void
    {
        $hotel = $this->hotel();
        for ($i = 0; $i < 21; $i++) {
            Room::create(['hotel_id' => $hotel->id, 'name' => 'Quarto '.$i]);
        }
        $response = $this->getJson('/api/rooms/availability?check_in=2027-01-01&check_out=2028-01-02&hotel_id='.$hotel->id)
            ->assertOk()->assertJsonPath('total', 21)->assertJsonCount(20, 'data');
        $this->assertStringContainsString('check_in=2027-01-01', $response->json('next_page_url'));
        $this->getJson($response->json('next_page_url'))->assertOk()->assertJsonCount(1, 'data');
    }
}
