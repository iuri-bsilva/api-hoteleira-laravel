<?php

namespace App\Http\Requests;

use App\Validation\ReservationDataValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('coupon_code'))) {
            $this->merge(['coupon_code' => strtoupper(trim($this->input('coupon_code')))]);
        }
    }

    public function rules(): array
    {
        return ReservationDataValidator::rules();
    }

    public function withValidator(Validator $validator): void
    {
        ReservationDataValidator::configure($validator);
    }
}
