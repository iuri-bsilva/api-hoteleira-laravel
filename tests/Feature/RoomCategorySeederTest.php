<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomCategory;
use Database\Seeders\RoomCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomCategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_are_idempotent_per_hotel_and_preserve_room_assignments(): void
    {
        $first = Hotel::create(['name' => 'Hotel 1']);
        Hotel::create(['name' => 'Hotel 2']);
        $this->seed(RoomCategorySeeder::class);
        $category = RoomCategory::where('hotel_id', $first->id)->where('name', 'Standard')->firstOrFail();
        $room = Room::create(['hotel_id' => $first->id, 'name' => '101', 'room_category_id' => $category->id]);
        $this->seed(RoomCategorySeeder::class);
        $this->assertDatabaseCount('room_categories', 6);
        $this->assertSame($category->id, $room->fresh()->room_category_id);
        $this->assertDatabaseHas('room_categories', ['hotel_id' => $first->id, 'name' => 'Suíte']);
    }
}
