<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\RoomCategory;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoomCategoryController extends Controller
{
    public function index(Request $request, Hotel $hotel)
    {
        $request->user()->requireHotelAccess($hotel->id);

        return RoomCategory::where('hotel_id', $hotel->id)->withCount('rooms')->orderBy('id')->paginate(20);
    }

    public function store(Request $request, Hotel $hotel)
    {
        $request->user()->requireHotelAccess($hotel->id, true);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('room_categories')->where('hotel_id', $hotel->id)]]);
        $category = new RoomCategory($data);
        $category->hotel_id = $hotel->id;
        try {
            $category->save();
        } catch (QueryException $error) {
            if (RoomCategory::where('hotel_id', $hotel->id)->where('name', $data['name'])->exists()) {
                throw ValidationException::withMessages(['name' => 'Categoria já cadastrada neste hotel.']);
            }
            throw $error;
        }

        return response()->json($category->fresh(), 201);
    }

    public function availability(Request $request, Hotel $hotel)
    {
        $request->user()->requireHotelAccess($hotel->id);
        $request->validate(['check_in' => 'bail|required|string|date_format:Y-m-d', 'check_out' => 'bail|required|string|date_format:Y-m-d']);
        $data = $request->validate(['check_in' => 'required|date_format:Y-m-d', 'check_out' => 'required|date_format:Y-m-d|after:check_in']);
        if (CarbonImmutable::parse($data['check_in'])->diffInDays(CarbonImmutable::parse($data['check_out'])) > 366) {
            throw ValidationException::withMessages(['check_out' => 'Máximo de 366 diárias.']);
        }

        return RoomCategory::where('hotel_id', $hotel->id)->withCount('rooms')
            ->withCount(['rooms as available_count' => fn ($rooms) => $rooms->whereDoesntHave('reservations', fn ($reservations) => $reservations
                ->where('check_in', '<', $data['check_out'])->where('check_out', '>', $data['check_in']))])
            ->orderBy('id')->paginate(20)->withQueryString();
    }
}
