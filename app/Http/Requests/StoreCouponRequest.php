<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        $this->user()->requireHotelAccess($this->route('hotel')->id, true);

        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons')->where('hotel_id', $this->route('hotel')->id)],
            'amount' => 'required|numeric|min:0.01|max:9999999999.99|decimal:0,2',
            'type' => ['sometimes', 'required', Rule::in(['fixed', 'percentage'])],
            'minimum_subtotal' => 'sometimes|required|numeric|min:0|max:9999999999.99|decimal:0,2',
            'valid_from' => 'nullable|date_format:Y-m-d',
            'valid_until' => 'nullable|date_format:Y-m-d',
            'active' => 'sometimes|required|boolean',
        ];
    }
}
