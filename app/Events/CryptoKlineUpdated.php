<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CryptoKlineUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $exchange;
    public string $symbol;
    public string $timeframe;
    public array $data;
    public int $timestamp;

    public function __construct(string $exchange, string $symbol, string $timeframe, array $data)
    {
        $this->exchange = $exchange;
        $this->symbol = $symbol;
        $this->timeframe = $timeframe;
        $this->data = $data;
        $this->timestamp = time();
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("crypto.kline.{$this->exchange}.{$this->symbol}.{$this->timeframe}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'kline.updated';
    }
}
