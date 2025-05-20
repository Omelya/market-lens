<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',
            'password_confirmation' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Поточний пароль обов\'язковий',
            'password.required' => 'Новий пароль обов\'язковий',
            'password.min' => 'Пароль повинен містити принаймні 8 символів',
            'password.confirmed' => 'Підтвердження паролю не співпадає',
            'password.regex' => 'Пароль повинен містити принаймні одну велику літеру, одну малу літеру, одну цифру та один спеціальний символ',
            'password_confirmation.required' => 'Підтвердження паролю обов\'язкове',
        ];
    }
}
