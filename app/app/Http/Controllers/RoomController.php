<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomController extends Controller
{
    public function index()
    {
        return Room::with('hotel')->paginate(20);
    }

    public function show(Room $room)
    {
        return $room->load('hotel');
    }

    public function store(Request $request)
    {
        return response()->json(Room::create($request->validate([
            'hotel_id' => 'required|integer|exists:hotels,id', 'name' => 'required|string|max:255',
        ])), 201);
    }

    public function update(Request $request, Room $room)
    {
        $data = $request->validate(['name' => 'sometimes|required|string|max:255', 'hotel_id' => 'sometimes|required|integer|exists:hotels,id']);

        return DB::transaction(function () use ($room, $data) {
            $room = Room::lockForUpdate()->findOrFail($room->id);
            abort_if(isset($data['hotel_id']) && $data['hotel_id'] != $room->hotel_id && $room->reservations()->exists(), 409, 'Quarto com reservas não pode mudar de hotel.');
            $room->update($data);

            return $room;
        });
    }

    public function destroy(Room $room)
    {
        return DB::transaction(function () use ($room) {
            $room = Room::lockForUpdate()->findOrFail($room->id);
            abort_if($room->reservations()->exists(), 409, 'Quarto possui reservas.');
            $room->delete();

            return response()->json(['message' => 'Quarto excluído.']);
        });
    }
}
