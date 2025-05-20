<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExchangeTokenRequest;
use App\Http\Resources\Auth\UserResource;
use App\Models\ExchangeApiKey;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function me(Request $request): UserResource
    {
        return $request
            ->user()
            ->toResource(UserResource::class)
            ->additional(['exchange_api_keys' => $request->user()->exchangeApiKeys]);
    }

    public function addApiKey(ExchangeTokenRequest $request): JsonResponse
    {
        try {
            $exchangeApiKey = ExchangeApiKey::create([
                ...$request->validated(),
                'user_id' => $request->user()->id,
            ]);

            return response()
                ->json($exchangeApiKey, 201);
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return response()
                ->json(
                    ['message' => 'Failed to add exchange token'],
                    500,
                );
        }
    }
}
