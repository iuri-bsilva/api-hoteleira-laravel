<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class StayPeriodRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'check_in' => 'bail|required|string|date_format:Y-m-d',
            'check_out' => 'bail|required|string|date_format:Y-m-d',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            // Compara apenas datas cujos tipos e formatos já foram validados.
            $dateValidator = \Illuminate\Support\Facades\Validator::make(
                $this->only('check_in', 'check_out'), ['check_out' => 'after:check_in'],
            );
            if ($dateValidator->fails()) {
                $validator->errors()->merge($dateValidator->errors());

                return;
            }
            if (CarbonImmutable::parse($this->input('check_in'))->diffInDays(CarbonImmutable::parse($this->input('check_out'))) > 366) {
                $validator->errors()->add('check_out', 'Máximo de 366 diárias.');
            }
        });
    }
}
