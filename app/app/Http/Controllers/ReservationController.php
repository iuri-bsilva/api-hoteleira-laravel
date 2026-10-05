<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function store(Request $request, ReservationService $service)
    {
        return response()->json($service->save($request->all(), user: $request->user()), 201);
    }

    public function index(Request $request)
    {
        return Reservation::whereHas('room', fn ($query) => $query->whereIn('hotel_id', $request->user()->hotelIds()))->with('room.hotel', 'guests', 'dailies', 'payments')->paginate(20);
    }

    public function show(Request $request, Reservation $reservation)
    {
        $request->user()->requireHotelAccess($reservation->room->hotel_id);

        return $reservation->load('room.hotel', 'guests', 'dailies', 'payments');
    }
}
