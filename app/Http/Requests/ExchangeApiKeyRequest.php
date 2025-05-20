<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExchangeApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exchange_id' => 'required|int|exists:exchanges,id',
            'name' => 'required|string|min:3|max:50',
            'api_key' => 'required|string|min:5',
            'api_secret' => 'string|min:5',
            'passphrase' => 'string|min:5',
        ];
    }

    public function messages(): array
    {
        return [
            'exchange_id.required' => 'ID обов\'язкове',
            'exchange_id.integer' => 'ID повинен бути цілим числом',
            'exchange_id.exists' => 'Вказаний обмін не існує',
            'name.required' => 'Назва обов\'язкова',
            'name.string' => 'Назва повинна бути рядком',
            'name.min' => 'Назва повинна бути не менше 3 символів',
            'name.max' => 'Назва повинна бути не більше 50 символів',
            'api_key.required' => 'API-ключ обов\'язковий',
            'api_key.string' => 'API-ключ повинен бути рядком',
            'api_key.min' => 'API-ключ повинен бути не менше 5 символів',
            'api_secret.string' => 'API-секрет повинен бути рядком',
            'api_secret.min' => 'API-секрет повинен бути не менше 5 символів',
            'passphrase.string' => 'Passphrase повинен бути рядком',
            'passphrase.min' => 'Passphrase повинен бути не менше 5 символів',
        ];
    }
}
