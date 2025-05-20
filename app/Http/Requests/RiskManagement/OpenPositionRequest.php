<?php

namespace App\Http\Requests\RiskManagement;

use Illuminate\Foundation\Http\FormRequest;

class OpenPositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'api_key_id' => 'required|integer|exists:exchange_api_keys,id,user_id,' . auth()->id(),
            'trading_pair_id' => 'required|integer|exists:trading_pairs,id',
            'direction' => 'required|string|in:buy,sell,long,short',
            'entry_price' => 'required|numeric|gt:0',
            'stop_loss_price' => 'required|numeric|gt:0',
            'take_profit_price' => 'nullable|numeric|gt:0',
            'risk_strategy_id' => 'nullable|integer|exists:risk_management_strategies,id,user_id,' . auth()->id(),
            'position_size' => 'nullable|numeric|gt:0',
            'leverage' => 'nullable|numeric|min:1',
            'order_type' => 'nullable|string|in:market,limit,stop_limit',
            'position_type' => 'nullable|string|in:manual,auto',
            'create_stop_loss' => 'nullable|boolean',
            'create_take_profit' => 'nullable|boolean',
            'order_params' => 'nullable|array',
            'stop_loss_params' => 'nullable|array',
            'take_profit_params' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'api_key_id.required' => "API ключ обов'язковий",
            'api_key_id.integer' => 'API ключ повинен бути числом',
            'api_key_id.exists' => 'Вказаний API ключ не існує або належить іншому користувачу',
            'trading_pair_id.required' => "Торгова пара обов'язкова",
            'trading_pair_id.integer' => 'Торгова пара повинна бути числом',
            'trading_pair_id.exists' => 'Вказана торгова пара не існує',
            'direction.required' => "Напрямок позиції обов'язковий",
            'direction.in' => 'Напрямок позиції повинен бути buy, sell, long або short',
            'entry_price.required' => "Ціна входу обов'язкова",
            'entry_price.numeric' => 'Ціна входу повинна бути числом',
            'entry_price.gt' => 'Ціна входу повинна бути більше нуля',
            'stop_loss_price.required' => "Ціна стоп-лосу обов'язкова",
            'stop_loss_price.numeric' => 'Ціна стоп-лосу повинна бути числом',
            'stop_loss_price.gt' => 'Ціна стоп-лосу повинна бути більше нуля',
            'take_profit_price.numeric' => 'Ціна тейк-профіту повинна бути числом',
            'take_profit_price.gt' => 'Ціна тейк-профіту повинна бути більше нуля',
            'risk_strategy_id.integer' => 'ID стратегії ризик-менеджменту повинен бути числом',
            'risk_strategy_id.exists' => 'Вказана стратегія ризик-менеджменту не існує або належить іншому користувачу',
            'position_size.numeric' => 'Розмір позиції повинен бути числом',
            'position_size.gt' => 'Розмір позиції повинен бути більше нуля',
            'leverage.numeric' => 'Кредитне плече повинно бути числом',
            'leverage.min' => 'Кредитне плече повинно бути не менше 1',
            'order_type.in' => 'Тип ордера повинен бути market, limit або stop_limit',
            'position_type.in' => 'Тип позиції повинен бути manual або auto',
            'create_stop_loss.boolean' => 'Створення стоп-лосу повинно бути логічним значенням',
            'create_take_profit.boolean' => 'Створення тейк-профіту повинно бути логічним значенням',
            'order_params.array' => 'Параметри ордера повинні бути масивом',
            'stop_loss_params.array' => 'Параметри стоп-лосу повинні бути масивом',
            'take_profit_params.array' => 'Параметри тейк-профіту повинні бути масивом',
        ];
    }
}
