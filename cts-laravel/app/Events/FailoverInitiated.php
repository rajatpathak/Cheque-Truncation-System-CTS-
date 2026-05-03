<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FailoverInitiated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $initiatedBy,
        public readonly string $reason,
        public readonly string $timestamp = ''
    ) {
        $this->timestamp = now()->toIso8601String();
    }
}
