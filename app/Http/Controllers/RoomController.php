<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoomAvailabilityRequest;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Interfaces\Services\RoomServiceInterface;
use App\Models\Room;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function __construct(private readonly RoomServiceInterface $rooms) {}

    public function availability(RoomAvailabilityRequest $request)
    {
        return $this->rooms->available($request->user(), $request->validated())->withQueryString();
    }

    public function index(Request $request)
    {
        return $this->rooms->list($request->user());
    }

    public function show(Request $request, Room $room)
    {
        return $this->rooms->show($request->user(), $room);
    }

    public function store(StoreRoomRequest $request)
    {
        return response()->json($this->rooms->create($request->user(), $request->validated()), 201);
    }

    public function update(UpdateRoomRequest $request, Room $room)
    {
        return $this->rooms->update($request->user(), $room, $request->validated());
    }

    public function destroy(Request $request, Room $room)
    {
        $this->rooms->delete($request->user(), $room);

        return response()->json(['message' => 'Quarto excluído.']);
    }
}
