<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomCategory;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomController extends Controller
{
    public function availability(Request $request)
    {
        $request->validate([
            'check_in' => 'bail|required|string|date_format:Y-m-d',
            'check_out' => 'bail|required|string|date_format:Y-m-d',
        ]);
        $data = $request->validate([
            'check_in' => 'required|date_format:Y-m-d',
            'check_out' => 'required|date_format:Y-m-d|after:check_in',
            'hotel_id' => 'sometimes|required|integer|exists:hotels,id',
        ]);
        if (CarbonImmutable::parse($data['check_in'])->diffInDays(CarbonImmutable::parse($data['check_out'])) > 366) {
            throw ValidationException::withMessages(['check_out' => 'Máximo de 366 diárias.']);
        }
        if (isset($data['hotel_id'])) {
            $request->user()->requireHotelAccess($data['hotel_id']);
        }

        return Room::whereIn('hotel_id', $request->user()->hotelIds())
            ->when(isset($data['hotel_id']), fn ($query) => $query->where('hotel_id', $data['hotel_id']))
            ->whereDoesntHave('reservations', fn ($query) => $query
                ->where('check_in', '<', $data['check_out'])
                ->where('check_out', '>', $data['check_in']))
            ->with('hotel', 'category')->orderBy('id')->paginate(20)->withQueryString();
    }

    public function index(Request $request)
    {
        return Room::whereIn('hotel_id', $request->user()->hotelIds())->with('hotel', 'category')->paginate(20);
    }

    public function show(Request $request, Room $room)
    {
        $request->user()->requireHotelAccess($room->hotel_id);

        return $room->load('hotel', 'category');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'hotel_id' => 'required|integer|exists:hotels,id', 'name' => 'required|string|max:255',
            'room_category_id' => 'nullable|integer|exists:room_categories,id',
        ]);
        $request->user()->requireHotelAccess($data['hotel_id'], true);
        $this->checkCategory($data['room_category_id'] ?? null, $data['hotel_id']);

        return response()->json(Room::create($data), 201);
    }

    public function update(Request $request, Room $room)
    {
        $request->user()->requireHotelAccess($room->hotel_id, true);
        $data = $request->validate(['name' => 'sometimes|required|string|max:255', 'hotel_id' => 'sometimes|required|integer|exists:hotels,id', 'room_category_id' => 'sometimes|nullable|integer|exists:room_categories,id']);

        return DB::transaction(function () use ($request, $room, $data) {
            $room = Room::lockForUpdate()->findOrFail($room->id);
            $request->user()->requireHotelAccess($room->hotel_id, true);
            if (isset($data['hotel_id'])) {
                $request->user()->requireHotelAccess($data['hotel_id'], true);
            }
            abort_if(isset($data['hotel_id']) && $data['hotel_id'] != $room->hotel_id && $room->reservations()->exists(), 409, 'Quarto com reservas não pode mudar de hotel.');
            $this->checkCategory(array_key_exists('room_category_id', $data) ? $data['room_category_id'] : $room->room_category_id, $data['hotel_id'] ?? $room->hotel_id);
            $room->update($data);

            return $room;
        });
    }

    public function destroy(Request $request, Room $room)
    {
        return DB::transaction(function () use ($request, $room) {
            $room = Room::lockForUpdate()->findOrFail($room->id);
            $request->user()->requireHotelAccess($room->hotel_id, true);
            abort_if($room->reservations()->exists(), 409, 'Quarto possui reservas.');
            $room->delete();

            return response()->json(['message' => 'Quarto excluído.']);
        });
    }

    private function checkCategory(?int $categoryId, int $hotelId): void
    {
        if ($categoryId !== null && ! RoomCategory::whereKey($categoryId)->where('hotel_id', $hotelId)->exists()) {
            throw ValidationException::withMessages(['room_category_id' => 'Categoria deve pertencer ao hotel do quarto.']);
        }
    }
}
