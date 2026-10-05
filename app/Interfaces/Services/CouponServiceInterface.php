<?php

namespace App\Interfaces\Services;

use App\Models\Coupon;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CouponServiceInterface
{
    public function list(User $user, Hotel $hotel): LengthAwarePaginator;

    public function create(User $user, Hotel $hotel, array $data): Coupon;

    public function setActive(User $user, Hotel $hotel, Coupon $coupon, bool $active): Coupon;
}
