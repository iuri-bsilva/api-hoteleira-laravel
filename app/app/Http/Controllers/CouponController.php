<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Hotel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    public function index(Request $request, Hotel $hotel)
    {
        $request->user()->requireHotelAccess($hotel->id, true);

        return $hotel->coupons()->paginate(20);
    }

    public function store(Request $request, Hotel $hotel)
    {
        $request->user()->requireHotelAccess($hotel->id, true);
        if (is_string($request->input('code'))) {
            $request->merge(['code' => strtoupper(trim($request->input('code')))]);
        }
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons')->where('hotel_id', $hotel->id)],
            'amount' => 'required|numeric|min:0.01|max:9999999999.99|decimal:0,2',
            'type' => ['sometimes', 'required', Rule::in(['fixed', 'percentage'])],
            'minimum_subtotal' => 'sometimes|required|numeric|min:0|max:9999999999.99|decimal:0,2',
            'valid_from' => 'nullable|date_format:Y-m-d',
            'valid_until' => 'nullable|date_format:Y-m-d',
            'active' => 'sometimes|required|boolean',
        ]);
        if (($data['type'] ?? 'fixed') === 'percentage' && (float) $data['amount'] > 100) {
            throw ValidationException::withMessages(['amount' => 'Percentual deve ser maior que zero e no máximo 100.']);
        }
        if (! empty($data['valid_from']) && ! empty($data['valid_until']) && $data['valid_until'] < $data['valid_from']) {
            throw ValidationException::withMessages(['valid_until' => 'Fim da validade deve ser igual ou posterior ao início.']);
        }
        try {
            $coupon = $hotel->coupons()->create($data)->fresh();
        } catch (QueryException $exception) {
            if ($hotel->coupons()->where('code', $data['code'])->exists()) {
                throw ValidationException::withMessages(['code' => 'Código já cadastrado neste hotel.']);
            }
            throw $exception;
        }

        return response()->json($coupon, 201);
    }

    public function update(Request $request, Hotel $hotel, Coupon $coupon)
    {
        abort_unless($coupon->hotel_id === $hotel->id, 404);
        $request->user()->requireHotelAccess($hotel->id, true);
        $data = $request->validate(['active' => 'required|boolean']);

        return DB::transaction(function () use ($coupon, $data) {
            $coupon = Coupon::lockForUpdate()->findOrFail($coupon->id);
            $coupon->update($data);

            return $coupon;
        });
    }
}
