<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSecuritySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login_notifications' => 'boolean',
            'sensitive_action_verification' => 'boolean',
            'auto_logout_time' => 'integer|min:5|max:1440',
            'ip_whitelist' => 'nullable|array',
            'ip_whitelist.*' => 'ip',
            'trusted_devices' => 'nullable|array',
            'api_key_usage_notifications' => 'boolean',
            'block_suspicious_ip' => 'boolean',
            'max_failed_login_attempts' => 'integer|min:3|max:20',
            'block_duration_minutes' => 'integer|min:5|max:1440',
        ];
    }

    public function messages(): array
    {
        return [
            'ip_whitelist.*.ip' => 'Поле має бути правильною IP-адресою',
            'auto_logout_time.min' => 'Час автовиходу має бути щонайменше 5 хвилин',
            'auto_logout_time.max' => 'Час автовиходу не може перевищувати 1440 хвилин (24 години)',
            'max_failed_login_attempts.min' => 'Кількість невдалих спроб має бути щонайменше 3',
            'max_failed_login_attempts.max' => 'Кількість невдалих спроб не може перевищувати 20',
            'block_duration_minutes.min' => 'Тривалість блокування має бути щонайменше 5 хвилин',
            'block_duration_minutes.max' => 'Тривалість блокування не може перевищувати 1440 хвилин (24 години)',
        ];
    }
}
