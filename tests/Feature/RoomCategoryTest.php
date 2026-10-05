<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoomCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function hotel(string $role = 'manager'): Hotel
    {
        $hotel = Hotel::create(['name' => 'Hotel']);
        $user = User::factory()->create();
        $user->hotels()->attach($hotel->id, ['role' => $role]);
        Sanctum::actingAs($user);

        return $hotel;
    }

    private function payload(int $category): array
    {
        return ['room_category_id' => $category, 'check_in' => '2028-05-10', 'check_out' => '2028-05-11',
            'guests' => [['name' => 'Ana', 'last_name' => 'Silva', 'phone' => '123']],
            'dailies' => [['date' => '2028-05-10', 'value' => '100.00']]];
    }

    public function test_manager_creates_category_and_assigns_physical_rooms(): void
    {
        $hotel = $this->hotel();
        $url = '/api/hotels/'.$hotel->id.'/categories';
        $category = $this->postJson($url, ['name' => 'Standard'])->assertCreated()->json('id');
        $this->postJson($url, ['name' => 'Standard'])->assertUnprocessable();
        $room = $this->postJson('/api/rooms', ['hotel_id' => $hotel->id, 'name' => '101', 'room_category_id' => $category])->assertCreated()->json('id');
        $this->getJson('/api/rooms/'.$room)->assertOk()->assertJsonPath('category.name', 'Standard');
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.rooms_count', 1);
        $this->patchJson('/api/rooms/'.$room, ['room_category_id' => null])->assertOk();
        $this->getJson($url)->assertJsonPath('data.0.rooms_count', 0);
        $other = Hotel::create(['name' => 'Outro']);
        $foreign = new RoomCategory(['name' => 'Luxo']);
        $foreign->hotel_id = $other->id;
        $foreign->save();
        $this->patchJson('/api/rooms/'.$room, ['room_category_id' => $foreign->id])->assertUnprocessable()->assertJsonValidationErrors('room_category_id');
        $this->postJson('/api/rooms', ['hotel_id' => $hotel->id, 'name' => '102', 'room_category_id' => $foreign->id])->assertUnprocessable();
    }

    public function test_capacity_is_allocated_until_exhausted_and_boundaries_are_free(): void
    {
        $hotel = $this->hotel();
        $url = '/api/hotels/'.$hotel->id.'/categories';
        $category = $this->postJson($url, ['name' => 'Standard'])->assertCreated()->json('id');
        $empty = $this->postJson($url, ['name' => 'Vazia'])->assertCreated()->json('id');
        $first = Room::create(['hotel_id' => $hotel->id, 'name' => '101', 'room_category_id' => $category]);
        $second = Room::create(['hotel_id' => $hotel->id, 'name' => '102', 'room_category_id' => $category]);
        Room::create(['hotel_id' => $hotel->id, 'name' => 'Sem categoria']);
        $query = $url.'/availability?check_in=2028-05-10&check_out=2028-05-11';
        $this->getJson($query)->assertOk()->assertJsonPath('data.0.rooms_count', 2)->assertJsonPath('data.0.available_count', 2)->assertJsonPath('data.1.available_count', 0);
        $this->postJson('/api/reservations', $this->payload($category))->assertCreated()->assertJsonPath('room_id', $first->id);
        $this->getJson($query)->assertJsonPath('data.0.available_count', 1);
        $this->postJson('/api/reservations', $this->payload($category))->assertCreated()->assertJsonPath('room_id', $second->id);
        $this->getJson($query)->assertJsonPath('data.0.available_count', 0);
        $this->postJson('/api/reservations', $this->payload($category))->assertConflict();
        $this->postJson('/api/reservations', $this->payload($empty))->assertConflict();
        $this->assertDatabaseCount('reservations', 2);
        $this->assertDatabaseCount('guests', 2);
        $next = $this->payload($category);
        $next['check_in'] = '2028-05-11';
        $next['check_out'] = '2028-05-12';
        $next['dailies'][0]['date'] = '2028-05-11';
        $this->postJson('/api/reservations', $next)->assertCreated()->assertJsonPath('room_id', $first->id);
    }

    public function test_invalid_selection_and_financial_rules_do_not_consume_inventory(): void
    {
        $hotel = $this->hotel();
        $category = $this->postJson('/api/hotels/'.$hotel->id.'/categories', ['name' => 'Standard'])->json('id');
        $room = Room::create(['hotel_id' => $hotel->id, 'name' => '101', 'room_category_id' => $category]);
        $this->postJson('/api/reservations', $this->payload($category) + ['room_id' => $room->id])->assertUnprocessable();
        $this->postJson('/api/reservations', $this->payload(9999))->assertUnprocessable();
        $this->postJson('/api/reservations', $this->payload($category) + ['discount' => '100.01'])->assertUnprocessable();
        $this->assertDatabaseCount('reservations', 0);
        $this->postJson('/api/reservations', $this->payload($category) + ['discount' => '20', 'service_fee' => '10', 'payments' => [['method' => 1, 'value' => '90']]])->assertCreated()->assertJsonPath('total', '90.00');
    }

    public function test_transfer_with_existing_category_rolls_back_until_category_is_removed(): void
    {
        $hotel = $this->hotel();
        $destination = Hotel::create(['name' => 'Destino']);
        auth()->user()->hotels()->attach($destination->id, ['role' => 'manager']);
        $category = $this->postJson('/api/hotels/'.$hotel->id.'/categories', ['name' => 'Standard'])->assertCreated()->json('id');
        $room = Room::create(['hotel_id' => $hotel->id, 'name' => 'Original', 'room_category_id' => $category]);
        $url = '/api/rooms/'.$room->id;

        $this->patchJson($url, ['hotel_id' => $destination->id, 'name' => 'Transferido'])
            ->assertUnprocessable()->assertJsonValidationErrors('room_category_id');
        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'hotel_id' => $hotel->id, 'name' => 'Original', 'room_category_id' => $category]);

        $this->patchJson($url, ['hotel_id' => $destination->id, 'name' => 'Transferido', 'room_category_id' => null])
            ->assertOk()->assertJsonPath('hotel_id', $destination->id)->assertJsonPath('room_category_id', null);
        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'hotel_id' => $destination->id, 'name' => 'Transferido', 'room_category_id' => null]);
    }

    public function test_viewer_reads_but_cannot_write_and_other_hotel_is_denied(): void
    {
        $hotel = $this->hotel('viewer');
        $category = new RoomCategory(['name' => 'Standard']);
        $category->hotel_id = $hotel->id;
        $category->save();
        $url = '/api/hotels/'.$hotel->id.'/categories';
        $this->getJson($url)->assertOk();
        $this->getJson($url.'/availability?check_in=2028-05-10&check_out=2028-05-11')->assertOk();
        $this->postJson($url, ['name' => 'Luxo'])->assertForbidden();
        $this->postJson('/api/reservations', $this->payload($category->id))->assertForbidden();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson($url)->assertForbidden();
        $this->postJson('/api/reservations', $this->payload($category->id))->assertForbidden();
    }

    public function test_grouped_availability_requires_a_single_room_free_for_the_entire_period(): void
    {
        $hotel = $this->hotel();
        $category = $this->postJson('/api/hotels/'.$hotel->id.'/categories', ['name' => 'Standard'])->assertCreated()->json('id');
        $this->postJson('/api/hotels/'.$hotel->id.'/categories', ['name' => 'Vazia'])->assertCreated();
        $first = Room::create(['hotel_id' => $hotel->id, 'name' => '101', 'room_category_id' => $category]);
        $second = Room::create(['hotel_id' => $hotel->id, 'name' => '102', 'room_category_id' => $category]);
        $data = $this->payload($category);
        unset($data['room_category_id']);
        $data['room_id'] = $first->id;
        $this->postJson('/api/reservations', $data)->assertCreated();
        $data['room_id'] = $second->id;
        $data['check_in'] = '2028-05-11';
        $data['check_out'] = '2028-05-12';
        $data['dailies'][0]['date'] = '2028-05-11';
        $this->postJson('/api/reservations', $data)->assertCreated();

        $url = '/api/hotels/'.$hotel->id.'/categories/availability';
        $this->getJson($url.'?check_in=2028-05-10&check_out=2028-05-12')
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.rooms_count', 2)->assertJsonPath('data.0.available_count', 0)
            ->assertJsonPath('data.1.rooms_count', 0)->assertJsonPath('data.1.available_count', 0);
        $this->getJson($url.'?check_in=2028-05-12&check_out=2028-05-13')
            ->assertOk()->assertJsonPath('data.0.available_count', 2);
    }

    public function test_dates_and_authentication(): void
    {
        $this->getJson('/api/hotels/1/categories')->assertUnauthorized();
        $hotel = $this->hotel();
        $url = '/api/hotels/'.$hotel->id.'/categories/availability';
        foreach (['', '?check_in=wrong&check_out=2028-01-01', '?check_in=2028-01-01&check_out=2028-01-01', '?check_in=2028-01-01&check_out=2030-01-01', '?check_in[]=2028-01-01&check_out=2028-01-02', '?check_in=2028-01-01&check_out[]=2028-01-02'] as $query) {
            $this->getJson($url.$query)->assertUnprocessable();
        }
    }
}
