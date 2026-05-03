<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ReplicationMonitorService
{
    /**
     * Check real-time replication lag between DC and DR.
     * Target: RPO <= 5 minutes per SLA.
     */
    public function getStatus(): array
    {
        $dcConfig = config('cts.grid.dc');
        $drConfig = config('cts.grid.dr');

        try {
            // Get last sequence from DC (primary)
            $dcSeq = $this->getLastSequence('dc');
            // Get last sequence from DR
            $drSeq = $this->getLastSequence('dr');

            $lag          = max(0, $dcSeq - $drSeq);
            $lagMinutes   = $this->estimateLagMinutes($lag);
            $rpoTarget    = config('cts.sla.rpo_minutes');
            $rpoBreached  = $lagMinutes > $rpoTarget;

            if ($rpoBreached) {
                Log::critical('RPO SLA breached!', ['lag_minutes' => $lagMinutes, 'target' => $rpoTarget]);
            }

            return [
                'dc_sequence'      => $dcSeq,
                'dr_sequence'      => $drSeq,
                'lag_records'      => $lag,
                'lag_minutes'      => $lagMinutes,
                'rpo_target_min'   => $rpoTarget,
                'rpo_status'       => $rpoBreached ? 'BREACH' : 'HEALTHY',
                'dc_status'        => $this->pingNode($dcConfig['host']),
                'dr_status'        => $this->pingNode($drConfig['host']),
                'checked_at'       => now()->toIso8601String(),
                'uptime_percent'   => $this->calculateUptime(),
            ];
        } catch (\Exception $e) {
            Log::error('Replication status check failed', ['error' => $e->getMessage()]);
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    /**
     * Initiate automatic failover from DC to DR.
     * Per RBI requirement: zero manual intervention.
     */
    public function initiateFailover(string $initiatedBy, string $reason): array
    {
        Log::critical('FAILOVER INITIATED', ['by' => $initiatedBy, 'reason' => $reason]);

        // 1. Verify DR is ready
        $drStatus = $this->pingNode(config('cts.grid.dr.host'));
        if ($drStatus !== 'UP') {
            return ['success' => false, 'message' => 'DR site is not reachable.'];
        }

        // 2. Switch connection string to DR
        Cache::put('active_node', 'dr', 86400);

        // 3. Notify all active sessions
        event(new \App\Events\FailoverInitiated($initiatedBy, $reason));

        // 4. Log for audit
        \App\Models\AuditTrail::create([
            'user_id'    => auth()->id(),
            'user_name'  => $initiatedBy,
            'action'     => 'FAILOVER_INITIATED',
            'module'     => 'MODULE_11_BCP',
            'request_body' => ['reason' => $reason],
            'timestamp'  => now(),
        ]);

        // 5. Notify bank IT team
        app(NotificationService::class)->notifyITTeam("CTS Failover initiated to DR by {$initiatedBy}. Reason: {$reason}");

        return [
            'success'      => true,
            'active_node'  => 'dr',
            'initiated_at' => now()->toIso8601String(),
            'message'      => 'Failover to DR site initiated successfully.',
        ];
    }

    public function healthCheck(): array
    {
        return [
            'dc'        => $this->checkNodeHealth('dc'),
            'dr'        => $this->checkNodeHealth('dr'),
            'uat'       => $this->checkNodeHealth('uat'),
            'active'    => Cache::get('active_node', 'dc'),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    private function getLastSequence(string $node): int
    {
        // Query Oracle sequence on respective DB connection
        $connection = "oracle_{$node}";
        return DB::connection($connection)
                 ->table('cts_instruments')
                 ->max('id') ?? 0;
    }

    private function estimateLagMinutes(int $recordLag): float
    {
        // Estimate: ~1000 instruments per minute throughput
        return round($recordLag / 1000, 2);
    }

    private function pingNode(string $host): string
    {
        try {
            $conn = @fsockopen($host, 443, $errno, $errstr, 3);
            if ($conn) {
                fclose($conn);
                return 'UP';
            }
        } catch (\Exception $e) {}
        return 'DOWN';
    }

    private function checkNodeHealth(string $node): array
    {
        $config = config("cts.grid.{$node}");
        return [
            'name'   => $config['name'],
            'host'   => $config['host'],
            'status' => $this->pingNode($config['host']),
        ];
    }

    private function calculateUptime(): float
    {
        // Retrieve uptime from monitoring table
        $total = \DB::table('cts_uptime_log')
            ->where('recorded_at', '>=', now()->subDays(30))
            ->count();
        $down  = \DB::table('cts_uptime_log')
            ->where('status', 'DOWN')
            ->where('recorded_at', '>=', now()->subDays(30))
            ->count();

        return $total > 0 ? round((($total - $down) / $total) * 100, 3) : 100.0;
    }
}
