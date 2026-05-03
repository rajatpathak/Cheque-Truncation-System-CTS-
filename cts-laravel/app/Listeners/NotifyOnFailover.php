<?php

namespace App\Listeners;

use App\Events\FailoverInitiated;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class NotifyOnFailover
{
    public function __construct(private NotificationService $notify) {}

    public function handle(FailoverInitiated $event): void
    {
        Log::critical('FAILOVER EVENT', [
            'initiated_by' => $event->initiatedBy,
            'reason'       => $event->reason,
            'timestamp'    => $event->timestamp,
        ]);

        $this->notify->notifyITTeam(
            "CTS FAILOVER INITIATED at {$event->timestamp} by {$event->initiatedBy}. Reason: {$event->reason}. System switched to DR site.",
            'CRITICAL'
        );
    }
}
