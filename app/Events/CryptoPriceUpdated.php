<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CryptoPriceUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $exchange;
    public string $symbol;
    public array $data;
    public int $timestamp;

    public function __construct(string $exchange, string $symbol, array $data)
    {
        $this->exchange = $exchange;
        $this->symbol = $symbol;
        $this->data = $data;
        $this->timestamp = time();
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("crypto.price.{$this->exchange}.{$this->symbol}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'price.updated';
    }
}
