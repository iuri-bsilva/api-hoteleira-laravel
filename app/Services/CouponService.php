<?php

namespace App\Services;

use App\Interfaces\Repositories\CouponRepositoryInterface;
use App\Interfaces\Services\CouponServiceInterface;
use App\Models\Coupon;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CouponService implements CouponServiceInterface
{
    public function __construct(private readonly CouponRepositoryInterface $coupons) {}

    public function list(User $user, Hotel $hotel): LengthAwarePaginator
    {
        $user->requireHotelAccess($hotel->id, true);

        return $this->coupons->paginate($hotel);
    }

    public function create(User $user, Hotel $hotel, array $data): Coupon
    {
        $user->requireHotelAccess($hotel->id, true);
        $data['code'] = strtoupper(trim($data['code']));
        if (($data['type'] ?? 'fixed') === 'percentage' && (float) $data['amount'] > 100) {
            throw ValidationException::withMessages(['amount' => 'Percentual deve ser maior que zero e no máximo 100.']);
        }
        if (! empty($data['valid_from']) && ! empty($data['valid_until']) && $data['valid_until'] < $data['valid_from']) {
            throw ValidationException::withMessages(['valid_until' => 'Fim da validade deve ser igual ou posterior ao início.']);
        }

        try {
            return $this->coupons->create($hotel, $data);
        } catch (QueryException $exception) {
            // A restrição única também protege contra cadastros simultâneos.
            if ($this->coupons->codeExists($hotel, $data['code'])) {
                throw ValidationException::withMessages(['code' => 'Código já cadastrado neste hotel.']);
            }
            throw $exception;
        }
    }

    public function setActive(User $user, Hotel $hotel, Coupon $coupon, bool $active): Coupon
    {
        $this->requireSameHotel($hotel, $coupon);
        $user->requireHotelAccess($hotel->id, true);

        return DB::transaction(function () use ($hotel, $coupon, $active) {
            $coupon = $this->coupons->lock($coupon->id);
            $this->requireSameHotel($hotel, $coupon);

            return $this->coupons->setActive($coupon, $active);
        });
    }

    private function requireSameHotel(Hotel $hotel, Coupon $coupon): void
    {
        if ($coupon->hotel_id !== $hotel->id) {
            throw new NotFoundHttpException;
        }
    }
}
