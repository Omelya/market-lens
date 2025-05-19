<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => "Ім'я користувача обов'язкове",
            'email.required' => "Email обов'язковий",
            'email.email' => 'Формат email невірний',
            'email.unique' => 'Користувач з таким email вже існує',
            'password.required' => "Пароль обов'язковий",
            'password.min' => 'Пароль має містити щонайменше 8 символів',
            'password.confirmed' => 'Підтвердження паролю не співпадає',
        ];
    }
}
