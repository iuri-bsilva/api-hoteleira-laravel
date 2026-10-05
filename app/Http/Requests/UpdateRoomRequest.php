<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        $this->user()->requireHotelAccess($this->route('room')->hotel_id, true);

        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'hotel_id' => 'sometimes|required|integer|exists:hotels,id',
            'room_category_id' => 'sometimes|nullable|integer|exists:room_categories,id',
        ];
    }
}
