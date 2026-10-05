<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryAvailabilityRequest;
use App\Http\Requests\StoreRoomCategoryRequest;
use App\Interfaces\Services\RoomCategoryServiceInterface;
use App\Models\Hotel;
use Illuminate\Http\Request;

class RoomCategoryController extends Controller
{
    public function __construct(private readonly RoomCategoryServiceInterface $categories) {}

    public function index(Request $request, Hotel $hotel)
    {
        return $this->categories->list($request->user(), $hotel);
    }

    public function store(StoreRoomCategoryRequest $request, Hotel $hotel)
    {
        return response()->json($this->categories->create($request->user(), $hotel, $request->validated()), 201);
    }

    public function availability(CategoryAvailabilityRequest $request, Hotel $hotel)
    {
        return $this->categories->available($request->user(), $hotel, $request->validated())->withQueryString();
    }
}
