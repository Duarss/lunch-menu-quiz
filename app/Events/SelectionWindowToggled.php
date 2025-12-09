<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SelectionWindowToggled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $weekCode,
        public bool $ready,
        public bool $open,
        public string $triggeredBy,
        public string $triggeredAt,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('selection-window');
    }

    public function broadcastAs(): string
    {
        return 'selection.window.toggled';
    }

    public function broadcastWith(): array
    {
        return [
            'week_code' => $this->weekCode,
            'ready' => $this->ready,
            'open' => $this->open,
            'triggered_by' => $this->triggeredBy,
            'triggered_at' => $this->triggeredAt,
        ];
    }
}
