<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['required', 'email:rfc', 'max:160', 'unique:users,email'],
            'phone'    => ['required', 'string', 'max:32', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role'     => ['required', 'in:passenger,driver'],
            'language' => ['nullable', 'in:fr,en'],

            // Champs supplémentaires si role=driver
            'license_number' => ['required_if:role,driver', 'nullable', 'string', 'max:64', 'unique:drivers,license_number'],
        ];
    }
}
