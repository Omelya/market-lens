<?php

namespace App\Console\Commands;

use App\Services\RiskManagement\TrailingStopService;
use Illuminate\Console\Command;

class UpdateTrailingStopsCommand extends Command
{
    protected $signature = 'risk:update-trailing-stops
                          {--verbose : Показати детальну інформацію про результати}';

    protected $description = 'Оновити трейлінг-стопи для всіх активних позицій';

    protected TrailingStopService $trailingStopService;

    public function __construct(TrailingStopService $trailingStopService)
    {
        parent::__construct();
        $this->trailingStopService = $trailingStopService;
    }

    public function handle(): void
    {
        $this->info('Оновлення трейлінг-стопів для активних позицій...');

        $result = $this->trailingStopService->updateAllTrailingStops();

        $this->info("Всього позицій з трейлінг-стопом: {$result['total']}");
        $this->info("Успішно оновлено: {$result['success']}");
        $this->info("Помилок: {$result['error']}");

        if ($this->option('verbose') && !empty($result['results'])) {
            $this->newLine();
            $this->info('Детальні результати:');

            foreach ($result['results'] as $positionResult) {
                $status = match ($positionResult['status']) {
                    'success' => '✅',
                    'error' => '❌',
                    'info' => 'ℹ️',
                    default => '⚪',
                };

                $this->line("{$status} Позиція #{$positionResult['position_id']}: {$positionResult['message']}");

                if ($positionResult['status'] === 'success' && isset($positionResult['previous_stop_loss'], $positionResult['new_stop_loss'])) {
                    $this->line("   Попередній стоп-лос: {$positionResult['previous_stop_loss']}");
                    $this->line("   Новий стоп-лос: {$positionResult['new_stop_loss']}");
                    $this->line("   Поточна ціна: {$positionResult['current_price']}");
                }

                if ($positionResult['status'] === 'error') {
                    $this->error("   {$positionResult['message']}");
                }
            }
        }
    }
}
