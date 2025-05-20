<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|min:2|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . auth()->id(),
            'timezone' => 'sometimes|string|timezone',
            'notification_preferences' => 'sometimes|array',
            'notification_preferences.email_notifications' => 'sometimes|boolean',
            'notification_preferences.price_alerts' => 'sometimes|boolean',
            'notification_preferences.trading_signals' => 'sometimes|boolean',
            'notification_preferences.security_alerts' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.min' => "Ім'я повинно містити принаймні 2 символи",
            'email.email' => 'Формат електронної пошти невірний',
            'email.unique' => 'Ця електронна пошта вже використовується',
            'timezone.timezone' => 'Вказаний часовий пояс не існує',
        ];
    }
}
