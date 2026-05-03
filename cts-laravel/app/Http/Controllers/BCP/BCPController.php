<?php

namespace App\Http\Controllers\BCP;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\ReplicationMonitorService;
use App\Services\NotificationService;

class BCPController extends Controller
{
    public function __construct(
        private ReplicationMonitorService $replication,
        private NotificationService       $notify
    ) {}

    public function status(Request $request): JsonResponse
    {
        return response()->json([
            'active_node'        => cache()->get('active_node', 'dc'),
            'replication'        => $this->replication->getStatus(),
            'health'             => $this->replication->healthCheck(),
            'sla'                => config('cts.sla'),
        ]);
    }

    public function replicationStatus(Request $request): JsonResponse
    {
        return response()->json($this->replication->getStatus());
    }

    public function initiateFailover(Request $request): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $result = $this->replication->initiateFailover(
            $request->user()->name,
            $request->reason
        );

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    public function switchback(Request $request): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:500']);

        // Verify DC is healthy before switching back
        $health = $this->replication->healthCheck();
        if ($health['dc']['status'] !== 'UP') {
            return response()->json(['error' => 'DC_NOT_READY', 'message' => 'Primary DC is not reachable.'], 503);
        }

        cache()->put('active_node', 'dc', 86400);
        $this->notify->notifyITTeam("CTS Switched back to Primary DC by {$request->user()->name}. Reason: {$request->reason}");

        return response()->json(['status' => 'SWITCHED_BACK', 'active_node' => 'dc']);
    }

    public function healthCheck(Request $request): JsonResponse
    {
        return response()->json($this->replication->healthCheck());
    }
}
