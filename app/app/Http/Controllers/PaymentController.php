<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Interfaces\Services\PaymentServiceInterface;
use App\Models\Reservation;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentServiceInterface $payments) {}

    public function index(Request $request, Reservation $reservation)
    {
        return response()->json($this->payments->summary($reservation, $request->user()));
    }

    public function store(StorePaymentRequest $request, Reservation $reservation)
    {
        $result = $this->payments->record($reservation, $request->user(), $request->validated());

        return response()->json(
            ['payment' => $result->payment, 'summary' => $result->summary],
            $result->created ? 201 : 200,
        );
    }
}
