<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class ExamHallUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $operation,
        public ?int $attempt_id = null,
        public ?array $participant = null,
        public string $occurred_at = '',
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('exam-hall.monitor');
    }
}
