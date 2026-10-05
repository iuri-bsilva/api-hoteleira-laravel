<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\RoomCategory;
use Illuminate\Database\Seeder;

class RoomCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (Hotel::orderBy('id')->cursor() as $hotel) {
            foreach (['Standard', 'Luxo', 'Suíte'] as $name) {
                RoomCategory::firstOrCreate(['hotel_id' => $hotel->id, 'name' => $name]);
            }
        }
    }
}
