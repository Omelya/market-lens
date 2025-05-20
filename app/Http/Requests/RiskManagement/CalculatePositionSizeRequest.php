<?php

namespace App\Http\Requests\RiskManagement;

use Illuminate\Foundation\Http\FormRequest;

class CalculatePositionSizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_balance' => 'required|numeric|gt:0',
            'risk_percentage' => 'required|numeric|min:0.01|max:100',
            'entry_price' => 'required|numeric|gt:0',
            'stop_loss_price' => 'required|numeric|gt:0',
            'direction' => 'required|string|in:buy,sell,long,short',
            'leverage' => 'nullable|numeric|min:1',
            'risk_reward_ratio' => 'nullable|numeric|min:0.1',
        ];
    }

    public function messages(): array
    {
        return [
            'account_balance.required' => "Баланс рахунку обов'язковий",
            'account_balance.numeric' => 'Баланс рахунку повинен бути числом',
            'account_balance.gt' => 'Баланс рахунку повинен бути більше нуля',
            'risk_percentage.required' => "Відсоток ризику обов'язковий",
            'risk_percentage.numeric' => 'Відсоток ризику повинен бути числом',
            'risk_percentage.min' => 'Відсоток ризику повинен бути не менше 0.01%',
            'risk_percentage.max' => 'Відсоток ризику не може перевищувати 100%',
            'entry_price.required' => "Ціна входу обов'язкова",
            'entry_price.numeric' => 'Ціна входу повинна бути числом',
            'entry_price.gt' => 'Ціна входу повинна бути більше нуля',
            'stop_loss_price.required' => "Ціна стоп-лосу обов'язкова",
            'stop_loss_price.numeric' => 'Ціна стоп-лосу повинна бути числом',
            'stop_loss_price.gt' => 'Ціна стоп-лосу повинна бути більше нуля',
            'direction.required' => "Напрямок позиції обов'язковий",
            'direction.in' => 'Напрямок позиції повинен бути buy, sell, long або short',
            'leverage.numeric' => 'Кредитне плече повинно бути числом',
            'leverage.min' => 'Кредитне плече повинно бути не менше 1',
            'risk_reward_ratio.numeric' => 'Співвідношення ризик/прибуток повинно бути числом',
            'risk_reward_ratio.min' => 'Співвідношення ризик/прибуток повинно бути не менше 0.1',
        ];
    }
}
