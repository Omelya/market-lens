<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExchangeApiKeyRequest;
use App\Http\Requests\UpdateApiKeyRequest;
use App\Http\Resources\ExchangeApiKeyResource;
use App\Models\ExchangeApiKey;
use App\Models\ExchangeApiKeyLog;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ExchangeApiKeyController extends Controller
{
    /**
     * @throws \Throwable
     */
    public function index(Request $request): JsonResource
    {
        $query = Auth::user()
            ?->exchangeApiKeys()
            ->with('exchange');

        if ($request->has('exchange_id')) {
            $query->where('exchange_id', $request->exchange_id);
        }

        if ($request->has('active_only') && $request->active_only) {
            $query->where('is_active', true);
        }

        return $query
            ->get()
            ->toResourceCollection(ExchangeApiKeyResource::class);
    }

    /**
     * @throws \Throwable
     */
    public function show(int $id): JsonResource
    {
        return Auth::user()
            ?->exchangeApiKeys()
            ->with('exchange')
            ->findOrFail($id)
            ?->toResource(ExchangeApiKeyResource::class);
    }

    public function store(ExchangeApiKeyRequest $request): JsonResponse
    {
        $user = Auth::user();

        $keysCount = $user->exchangeApiKeys()->count();

        if ($keysCount >= 5) {
            return response()->json([
                'status' => 'error',
                'message' => 'Досягнуто ліміт кількості API ключів (5)'
            ], 422);
        }

        try {
            $apiKey = new ExchangeApiKey($request->validated());

            $apiKey->user_id = $user->id;
            $apiKey->save();

            $this->logApiKeyAction($apiKey, 'create');

            return $apiKey
                ->toResource(ExchangeApiKeyResource::class)
                ->response()
                ->setStatusCode(201);
        } catch (\Exception $e) {
            Log::error('Помилка створення API ключа', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Не вдалося створити API ключ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @throws \Throwable
     */
    public function update(UpdateApiKeyRequest $request, int $id): JsonResponse|JsonResource
    {
        $user = Auth::user();
        $apiKey = $user->exchangeApiKeys()->findOrFail($id);

        try {
            $this->logApiKeyAction($apiKey, 'update', [
                'previous' => [
                    'name' => $apiKey->name,
                    'is_active' => $apiKey->is_active,
                    'is_test_net' => $apiKey->is_test_net,
                    'trading_enabled' => $apiKey->trading_enabled,
                ],
            ]);

            $apiKey->update($request->validated());

            return $apiKey
                ->toResource(ExchangeApiKeyResource::class);
        } catch (\Exception $e) {
            Log::error('Помилка оновлення API ключа', [
                'user_id' => $user->id,
                'api_key_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Не вдалося оновити API ключ: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();
        $apiKey = $user->exchangeApiKeys()->findOrFail($id);

        $openPositionsCount = $apiKey
            ->tradingPositions()
            ->where('status', 'open')
            ->count();

        if ($openPositionsCount > 0) {
            return response()->json([
                'status' => 'error',
                'message' => "Неможливо видалити ключ: знайдено {$openPositionsCount} відкритих позицій"
            ], 422);
        }

        try {
            $this->logApiKeyAction($apiKey, 'delete');

            $apiKey->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'API ключ видалено успішно'
            ]);
        } catch (\Exception $e) {
            Log::error('Помилка видалення API ключа', [
                'user_id' => $user->id,
                'api_key_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Не вдалося видалити API ключ: ' . $e->getMessage()
            ], 500);
        }
    }

    public function verify(int $id): JsonResponse
    {
        $user = Auth::user();
        $apiKey = $user->exchangeApiKeys()->findOrFail($id);

        try {
            $exchange = ExchangeFactory::createWithApiKey($id);
            $balanceResult = $exchange->getBalance();

            $apiKey->verified_at = now();
            $apiKey->save();

            $this->logApiKeyAction($apiKey, 'verify', ['success' => true]);

            return response()->json([
                'status' => 'success',
                'message' => 'API ключ успішно перевірено',
                'data' => [
                    'balance_summary' => $this->summarizeBalance($balanceResult),
                    'verified_at' => $apiKey->verified_at,
                ]
            ]);
        } catch (\Exception $e) {
            $this->logApiKeyAction($apiKey, 'verify', [
                'success' => false,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Перевірка API ключа не вдалася: ' . $e->getMessage()
            ], 422);
        }
    }

    public function getActivityLog(int $id): JsonResponse
    {
        $user = Auth::user();
        $apiKey = $user->exchangeApiKeys()->findOrFail($id);

        $logs = ExchangeApiKeyLog
            ::where('exchange_api_key_id', $apiKey->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $logs
        ]);
    }

    protected function summarizeBalance(array $balanceResult): array
    {
        $total = 0;
        $mainCurrencies = [];

        foreach ($balanceResult['total'] as $currency => $amount) {
            if ($amount > 0) {
                $mainCurrencies[$currency] = $amount;
            }

            // В ідеалі тут мав би бути розрахунок в USD на основі поточних курсів
            // але це спрощена версія
            if ($currency === 'USDT' || $currency === 'USDC' || $currency === 'USD') {
                $total += $amount;
            }
        }

        return [
            'total_balance' => $total,
            'main_currencies' => array_slice($mainCurrencies, 0, 5, true)
        ];
    }

    protected function logApiKeyAction(ExchangeApiKey $apiKey, string $action, array $details = []): void
    {
        ExchangeApiKeyLog::create([
            'exchange_api_key_id' => $apiKey->id,
            'action' => $action,
            'details' => $details,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
