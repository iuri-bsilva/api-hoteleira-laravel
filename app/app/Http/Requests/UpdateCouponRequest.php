<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless($this->route('coupon')->hotel_id === $this->route('hotel')->id, 404);
        $this->user()->requireHotelAccess($this->route('hotel')->id, true);

        return true;
    }

    public function rules(): array
    {
        return ['active' => 'required|boolean'];
    }
}
