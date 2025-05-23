<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Jobs\BroadcastCryptoDataJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
//        $schedule
//            ->job(new BroadcastCryptoDataJob('all', 'all'), 'broadcasts')
//            ->everyMinute()
//            ->withoutOverlapping();

        $schedule
            ->command('indicators:calculate --exchange=all --pair=all --timeframe=1d')
            ->cron('0 */3 * * *')
            ->withoutOverlapping();

        $schedule
            ->command('patterns:analyze --exchange=all --pair=all --timeframe=1d --generate-signals')
            ->cron('0 */6 * * *')
            ->withoutOverlapping();

        $schedule
            ->command('indicators:calculate --exchange=all --pair=all --timeframe=1d,4h,1h')
            ->dailyAt('01:00')
            ->withoutOverlapping();

        $schedule
            ->command('patterns:analyze --exchange=all --pair=all --timeframe=1d,4h,1h --generate-signals')
            ->dailyAt('02:00')
            ->withoutOverlapping();

        $schedule
            ->command('risk:update-trailing-stops')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        $schedule
            ->command('load:last-historical-data --timeframe=1m')
            ->everyMinute()
            ->withoutOverlapping();

        $schedule
            ->command('load:last-historical-data --timeframe=5m')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        $schedule
            ->command('load:last-historical-data --timeframe=15m')
            ->everyFifteenMinutes()
            ->withoutOverlapping();

        $schedule
            ->command('load:last-historical-data --timeframe=30m')
            ->cron('*/30 * * * *')
            ->withoutOverlapping();

        $schedule
            ->command('load:last-historical-data --timeframe=1h')
            ->hourly()
            ->withoutOverlapping();

        $schedule
            ->command('load:last-historical-data --timeframe=4h')
            ->cron('0 */4 * * *')
            ->withoutOverlapping();

        $schedule
            ->command('load:last-historical-data --timeframe=1d')
            ->dailyAt('00:01')
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
