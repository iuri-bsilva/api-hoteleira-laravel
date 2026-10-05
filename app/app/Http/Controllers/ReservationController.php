<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Interfaces\Services\ReservationServiceInterface;
use App\Models\Reservation;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function __construct(private readonly ReservationServiceInterface $reservations) {}

    public function store(StoreReservationRequest $request)
    {
        return response()->json($this->reservations->save($request->validated(), user: $request->user()), 201);
    }

    public function index(Request $request)
    {
        return $this->reservations->list($request->user());
    }

    public function show(Request $request, Reservation $reservation)
    {
        return $this->reservations->show($request->user(), $reservation);
    }
}
