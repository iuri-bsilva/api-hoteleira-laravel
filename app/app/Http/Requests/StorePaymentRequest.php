<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $this->user()->requireHotelAccess($this->route('reservation')->room->hotel_id, true);

        return true;
    }

    public function rules(): array
    {
        return [
            'method' => 'required|integer|min:1|max:65535',
            'value' => 'required|numeric|min:0.01|max:9999999999.99|decimal:0,2',
            'idempotency_key' => 'required|string|uuid',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('idempotency_key'))) {
            $this->merge(['idempotency_key' => strtolower($this->input('idempotency_key'))]);
        }
    }
}
