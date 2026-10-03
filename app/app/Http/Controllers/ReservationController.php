<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function store(Request $request, ReservationService $service)
    {
        return response()->json($service->save($request->all()), 201);
    }

    public function index()
    {
        return Reservation::with('room.hotel', 'guests', 'dailies', 'payments')->paginate(20);
    }

    public function show(Reservation $reservation)
    {
        return $reservation->load('room.hotel', 'guests', 'dailies', 'payments');
    }
}
