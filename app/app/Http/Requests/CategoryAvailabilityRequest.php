<?php

namespace App\Http\Requests;

class CategoryAvailabilityRequest extends StayPeriodRequest
{
    public function authorize(): bool
    {
        $this->user()->requireHotelAccess($this->route('hotel')->id);

        return true;
    }
}
