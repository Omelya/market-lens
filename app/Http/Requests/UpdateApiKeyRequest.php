<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|min:3|max:50',
            'api_key' => 'sometimes|string|min:5',
            'api_secret' => 'sometimes|nullable|string|min:5',
            'passphrase' => 'sometimes|nullable|string|min:5',
            'is_test_net' => 'sometimes|boolean',
            'trading_enabled' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'permissions' => 'sometimes|array',
            'permissions_data' => 'sometimes|array',
        ];
    }

    public function messages(): array
    {
        return [
            'name.min' => 'Назва повинна містити принаймні 3 символи',
            'name.max' => 'Назва не може перевищувати 50 символів',
            'api_key.min' => 'API ключ повинен містити принаймні 5 символів',
            'api_secret.min' => 'API секрет повинен містити принаймні 5 символів',
            'passphrase.min' => 'Парольна фраза повинна містити принаймні 5 символів',
        ];
    }
}
