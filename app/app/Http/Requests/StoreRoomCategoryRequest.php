<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $this->user()->requireHotelAccess($this->route('hotel')->id, true);

        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100', Rule::unique('room_categories')->where('hotel_id', $this->route('hotel')->id)]];
    }
}
