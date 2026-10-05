<?php

namespace App\Http\Controllers;

use App\Interfaces\Services\HotelServiceInterface;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    public function __construct(private readonly HotelServiceInterface $hotels) {}

    public function index(Request $request)
    {
        return $this->hotels->list($request->user());
    }
}
