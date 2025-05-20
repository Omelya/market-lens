<?php

namespace App\Http\Requests\RiskManagement;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [];

        if ($this->routeIs('*.updateStopLoss')) {
            $rules['stop_loss_price'] = 'required|numeric|gt:0';
            $rules['stop_loss_params'] = 'nullable|array';
        }

        if ($this->routeIs('*.updateTakeProfit')) {
            $rules['take_profit_price'] = 'required|numeric|gt:0';
            $rules['take_profit_params'] = 'nullable|array';
        }

        if ($this->routeIs('*.close')) {
            $rules['exit_price'] = 'nullable|numeric|gt:0';
            $rules['order_type'] = 'nullable|string|in:market,limit';
            $rules['order_params'] = 'nullable|array';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'stop_loss_price.required' => "Ціна стоп-лосу обов'язкова",
            'stop_loss_price.numeric' => 'Ціна стоп-лосу повинна бути числом',
            'stop_loss_price.gt' => 'Ціна стоп-лосу повинна бути більше нуля',
            'stop_loss_params.array' => 'Параметри стоп-лосу повинні бути масивом',

            'take_profit_price.required' => "Ціна тейк-профіту обов'язкова",
            'take_profit_price.numeric' => 'Ціна тейк-профіту повинна бути числом',
            'take_profit_price.gt' => 'Ціна тейк-профіту повинна бути більше нуля',
            'take_profit_params.array' => 'Параметри тейк-профіту повинні бути масивом',

            'exit_price.numeric' => 'Ціна виходу повинна бути числом',
            'exit_price.gt' => 'Ціна виходу повинна бути більше нуля',
            'order_type.in' => 'Тип ордера повинен бути market або limit',
            'order_params.array' => 'Параметри ордера повинні бути масивом',
        ];
    }
}
