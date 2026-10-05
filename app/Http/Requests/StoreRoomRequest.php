<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hotel_id' => 'required|integer|exists:hotels,id',
            'name' => 'required|string|max:255',
            'room_category_id' => 'nullable|integer|exists:room_categories,id',
        ];
    }
}
