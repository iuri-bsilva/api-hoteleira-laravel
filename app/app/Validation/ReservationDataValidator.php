<?php

namespace App\Validation;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReservationDataValidator
{
    public function validate(array $data): array
    {
        if (array_key_exists('room_id', $data) && array_key_exists('room_category_id', $data)) {
            throw ValidationException::withMessages(['room_category_id' => 'Informe somente room_id ou room_category_id.']);
        }
        if (isset($data['coupon_code']) && is_string($data['coupon_code'])) {
            $data['coupon_code'] = strtoupper(trim($data['coupon_code']));
        }
        $validator = Validator::make($data, self::rules());
        self::configure($validator);
        $data = $validator->validate();

        return $data;
    }

    public static function configure(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $dates = Validator::make($validator->getData(), ['check_out' => 'after:check_in']);
            $validator->errors()->merge($dates->errors());
        });
    }

    public static function rules(): array
    {
        return [
            'room_id' => 'required_without:room_category_id|integer|exists:rooms,id',
            'room_category_id' => 'required_without:room_id|integer|exists:room_categories,id',
            'check_in' => 'bail|required|string|date_format:Y-m-d', 'check_out' => 'bail|required|string|date_format:Y-m-d',
            'guests' => 'required|array|min:1|max:50', 'guests.*.name' => 'required|string|max:255',
            'guests.*.last_name' => 'required|string|max:255', 'guests.*.phone' => 'required|string|max:30',
            'dailies' => 'required|array|min:1|max:366', 'dailies.*.date' => 'required|date_format:Y-m-d|distinct',
            'dailies.*.value' => 'required|numeric|min:0|max:9999999999.99|decimal:0,2',
            'payments' => 'sometimes|array|max:100', 'payments.*.method' => 'required|integer|min:1|max:65535',
            'payments.*.value' => 'required|numeric|min:0|max:9999999999.99|decimal:0,2',
            'discount' => 'sometimes|required|numeric|min:0|max:9999999999.99|decimal:0,2',
            'service_fee' => 'sometimes|required|numeric|min:0|max:9999999999.99|decimal:0,2',
            'coupon_code' => 'sometimes|required|string|max:50|regex:/^[A-Z0-9_-]+$/',
        ];
    }
}
