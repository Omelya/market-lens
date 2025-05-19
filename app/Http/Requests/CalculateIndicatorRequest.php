<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalculateIndicatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'indicator_id' => 'required|integer|exists:technical_indicators,id',
            'trading_pair_id' => 'required|integer|exists:trading_pairs,id',
            'timeframe' => 'required|string|in:1m,3m,5m,15m,30m,1h,2h,4h,6h,8h,12h,1d,3d,1w,1M',
            'parameters' => 'nullable|array',
            'limit' => 'nullable|integer|min:1|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'indicator_id.required' => 'ID індикатора обов\'язковий',
            'indicator_id.integer' => 'ID індикатора повинен бути цілим числом',
            'indicator_id.exists' => 'Вказаний індикатор не існує',
            'trading_pair_id.required' => 'ID торгової пари обов\'язковий',
            'trading_pair_id.integer' => 'ID торгової пари повинен бути цілим числом',
            'trading_pair_id.exists' => 'Вказана торгова пара не існує',
            'timeframe.required' => 'Таймфрейм обов\'язковий',
            'timeframe.string' => 'Таймфрейм повинен бути рядком',
            'timeframe.in' => 'Невірний формат таймфрейму',
            'parameters.array' => 'Параметри повинні бути масивом',
            'limit.integer' => 'Ліміт повинен бути цілим числом',
            'limit.min' => 'Ліміт повинен бути не менше 1',
            'limit.max' => 'Ліміт повинен бути не більше 1000',
        ];
    }
}
