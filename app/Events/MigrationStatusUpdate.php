<?php

namespace App\Events;

use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MigrationStatusUpdate implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $message,
        public string $type = 'info',
        public ?int $teamId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("team.{$this->teamId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'status-update';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'type' => $this->type,
            'timestamp' => CarbonImmutable::now()->timestamp,
        ];
    }
}
