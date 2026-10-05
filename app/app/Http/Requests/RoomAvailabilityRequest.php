<?php

namespace App\Http\Requests;

class RoomAvailabilityRequest extends StayPeriodRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'hotel_id' => 'sometimes|required|integer|exists:hotels,id',
        ]);
    }
}
