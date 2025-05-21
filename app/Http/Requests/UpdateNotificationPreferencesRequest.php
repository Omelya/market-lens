<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email_notifications' => 'boolean',
            'price_alerts' => 'boolean',
            'trading_signals' => 'boolean',
            'security_alerts' => 'boolean',
            'telegram_notifications' => 'boolean|nullable',
            'telegram_chat_id' => 'string|nullable|required_if:telegram_notifications,true',
            'mobile_push' => 'boolean|nullable',
            'browser_notifications' => 'boolean|nullable',
            'notify_on_login' => 'boolean|nullable',
            'notify_on_api_key_usage' => 'boolean|nullable',
            'notify_on_position_change' => 'boolean|nullable',
            'quiet_hours_enabled' => 'boolean|nullable',
            'quiet_hours_start' => 'nullable|date_format:H:i|required_if:quiet_hours_enabled,true',
            'quiet_hours_end' => 'nullable|date_format:H:i|required_if:quiet_hours_enabled,true'
        ];
    }

    public function messages(): array
    {
        return [
            'telegram_chat_id.required_if' => 'Telegram Chat ID обов\'язковий, якщо увімкнено сповіщення в Telegram',
            'quiet_hours_start.required_if' => 'Час початку тихих годин обов\'язковий, якщо увімкнено тихі години',
            'quiet_hours_end.required_if' => 'Час закінчення тихих годин обов\'язковий, якщо увімкнено тихі години',
        ];
    }
}
