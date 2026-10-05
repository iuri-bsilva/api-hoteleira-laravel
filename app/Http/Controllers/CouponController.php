<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCouponRequest;
use App\Http\Requests\UpdateCouponRequest;
use App\Interfaces\Services\CouponServiceInterface;
use App\Models\Coupon;
use App\Models\Hotel;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function __construct(private readonly CouponServiceInterface $coupons) {}

    public function index(Request $request, Hotel $hotel)
    {
        return $this->coupons->list($request->user(), $hotel);
    }

    public function store(StoreCouponRequest $request, Hotel $hotel)
    {
        return response()->json($this->coupons->create($request->user(), $hotel, $request->validated()), 201);
    }

    public function update(UpdateCouponRequest $request, Hotel $hotel, Coupon $coupon)
    {
        return $this->coupons->setActive($request->user(), $hotel, $coupon, (bool) $request->validated('active'));
    }
}
