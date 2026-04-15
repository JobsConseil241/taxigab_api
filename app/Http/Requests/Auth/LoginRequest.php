<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // On accepte email OU phone pour le login
            'email'        => ['required_without:phone', 'nullable', 'email'],
            'phone'        => ['required_without:email', 'nullable', 'string', 'max:32'],
            'password'     => ['required', 'string'],
            'device_name'  => ['nullable', 'string', 'max:80'],
        ];
    }
}
