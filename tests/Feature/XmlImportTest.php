<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XmlImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_is_idempotent(): void
    {
        $this->artisan('hotels:import')->assertSuccessful();
        $room = Room::firstOrFail();
        $category = new RoomCategory(['name' => 'Standard']);
        $category->hotel_id = $room->hotel_id;
        $category->save();
        $room->update(['room_category_id' => $category->id]);
        $this->artisan('hotels:import')->assertSuccessful();
        $this->assertSame($category->id, $room->fresh()->room_category_id);
        foreach (['hotels' => 3, 'rooms' => 6, 'reservations' => 6, 'guests' => 6, 'dailies' => 18, 'payments' => 1] as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
    }

    public function test_invalid_original_xml_rolls_back_entire_batch(): void
    {
        $this->artisan('hotels:import', ['--path' => base_path('tests/Fixtures/original')])->assertFailed();
        foreach (['hotels', 'rooms', 'reservations', 'guests', 'dailies', 'payments'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }
}
