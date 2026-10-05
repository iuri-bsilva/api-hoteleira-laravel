<?php

namespace App\Interfaces\Repositories;

use App\Models\Coupon;
use App\Models\Hotel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CouponRepositoryInterface
{
    public function paginate(Hotel $hotel): LengthAwarePaginator;

    public function create(Hotel $hotel, array $data): Coupon;

    public function codeExists(Hotel $hotel, string $code): bool;

    public function lock(int $id): Coupon;

    public function setActive(Coupon $coupon, bool $active): Coupon;
}
