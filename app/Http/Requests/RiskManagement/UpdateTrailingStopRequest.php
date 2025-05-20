<?php

namespace App\Http\Requests\RiskManagement;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTrailingStopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'trailing_distance' => 'required|numeric|min:0.01',
            'activation_percentage' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'trailing_distance.required' => "Відстань трейлінг-стопу обов'язкова",
            'trailing_distance.numeric' => 'Відстань трейлінг-стопу повинна бути числом',
            'trailing_distance.min' => 'Відстань трейлінг-стопу повинна бути не менше 0.01%',
            'activation_percentage.numeric' => 'Відсоток активації повинен бути числом',
            'activation_percentage.min' => 'Відсоток активації не може бути негативним',
        ];
    }
}
