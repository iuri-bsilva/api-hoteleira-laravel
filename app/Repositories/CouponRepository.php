<?php

namespace App\Repositories;

use App\Interfaces\Repositories\CouponRepositoryInterface;
use App\Models\Coupon;
use App\Models\Hotel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CouponRepository implements CouponRepositoryInterface
{
    public function paginate(Hotel $hotel): LengthAwarePaginator
    {
        return $hotel->coupons()->paginate(20);
    }

    public function create(Hotel $hotel, array $data): Coupon
    {
        return $hotel->coupons()->create($data)->fresh();
    }

    public function codeExists(Hotel $hotel, string $code): bool
    {
        return $hotel->coupons()->where('code', $code)->exists();
    }

    public function lock(int $id): Coupon
    {
        return Coupon::lockForUpdate()->findOrFail($id);
    }

    public function setActive(Coupon $coupon, bool $active): Coupon
    {
        $coupon->update(['active' => $active]);

        return $coupon;
    }
}
