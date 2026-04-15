<?php

namespace App\Http\Requests\Ride;

use Illuminate\Foundation\Http\FormRequest;

class CreateRideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPassenger() === true;
    }

    public function rules(): array
    {
        return [
            'pickup_lat'      => ['required', 'numeric', 'between:-90,90'],
            'pickup_lng'      => ['required', 'numeric', 'between:-180,180'],
            'pickup_address'  => ['nullable', 'string', 'max:255'],

            'dropoff_lat'     => ['required', 'numeric', 'between:-90,90'],
            'dropoff_lng'     => ['required', 'numeric', 'between:-180,180'],
            'dropoff_address' => ['nullable', 'string', 'max:255'],

            'payment_method'       => ['nullable', 'in:cash,mobile_money'],
            'pricing_formula_id'   => ['nullable', 'integer', 'exists:pricing_formulas,id'],
        ];
    }
}
