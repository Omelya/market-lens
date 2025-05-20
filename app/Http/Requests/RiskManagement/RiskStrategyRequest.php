<?php

namespace App\Http\Requests\RiskManagement;

use Illuminate\Foundation\Http\FormRequest;

class RiskStrategyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'risk_percentage' => 'required|numeric|min:0.01|max:100',
            'risk_reward_ratio' => 'required|numeric|min:0.1',
            'use_trailing_stop' => 'boolean',
            'trailing_stop_activation' => 'nullable|numeric|min:0',
            'trailing_stop_distance' => 'nullable|numeric|min:0',
            'max_risk_per_trade' => 'nullable|numeric|min:0',
            'max_concurrent_trades' => 'nullable|integer|min:1',
            'max_daily_drawdown' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'boolean',
            'parameters' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => "Назва стратегії обов'язкова",
            'name.max' => 'Назва стратегії не може бути довше 255 символів',
            'risk_percentage.required' => "Відсоток ризику обов'язковий",
            'risk_percentage.numeric' => 'Відсоток ризику повинен бути числом',
            'risk_percentage.min' => 'Відсоток ризику повинен бути не менше 0.01%',
            'risk_percentage.max' => 'Відсоток ризику не може перевищувати 100%',
            'risk_reward_ratio.required' => "Співвідношення ризик/прибуток обов'язкове",
            'risk_reward_ratio.numeric' => 'Співвідношення ризик/прибуток повинно бути числом',
            'risk_reward_ratio.min' => 'Співвідношення ризик/прибуток повинно бути не менше 0.1',
            'trailing_stop_activation.numeric' => 'Активація трейлінг-стопу повинна бути числом',
            'trailing_stop_activation.min' => 'Активація трейлінг-стопу не може бути негативною',
            'trailing_stop_distance.numeric' => 'Відстань трейлінг-стопу повинна бути числом',
            'trailing_stop_distance.min' => 'Відстань трейлінг-стопу не може бути негативною',
            'max_risk_per_trade.numeric' => 'Максимальний ризик на угоду повинен бути числом',
            'max_risk_per_trade.min' => 'Максимальний ризик на угоду не може бути негативним',
            'max_concurrent_trades.integer' => 'Максимальна кількість одночасних угод повинна бути цілим числом',
            'max_concurrent_trades.min' => 'Максимальна кількість одночасних угод повинна бути не менше 1',
            'max_daily_drawdown.numeric' => 'Максимальний денний збиток повинен бути числом',
            'max_daily_drawdown.min' => 'Максимальний денний збиток не може бути негативним',
            'max_daily_drawdown.max' => 'Максимальний денний збиток не може перевищувати 100%',
        ];
    }
}
