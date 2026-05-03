<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Instrument;
use App\Models\ClearingSession;
use App\Models\ReturnInstrument;

class DashboardController extends Controller
{
    /**
     * Main CTS Operations Dashboard — real-time summary.
     * Cached for 60 seconds to avoid DB overload.
     */
    public function index(Request $request): JsonResponse
    {
        $date = $request->date ?? today()->toDateString();

        $data = Cache::remember("dashboard_{$date}", 60, function () use ($date) {
            return [
                'date'               => $date,
                'outward_summary'    => $this->outwardSummary($date),
                'inward_summary'     => $this->inwardSummary($date),
                'return_summary'     => $this->returnSummary($date),
                'fraud_summary'      => $this->fraudSummary($date),
                'session_status'     => $this->sessionStatus($date),
                'iqa_summary'        => $this->iqaSummary($date),
                'sla_status'         => $this->slaStatus(),
                'uptime'             => app(\App\Services\ReplicationMonitorService::class)->getStatus(),
            ];
        });

        return response()->json($data);
    }

    public function gridSummary(Request $request): JsonResponse
    {
        $date = $request->date ?? today()->toDateString();

        $summary = DB::table('cts_instruments')
            ->selectRaw("
                grid_code,
                COUNT(*) as total_instruments,
                SUM(amount_figures) as total_amount,
                SUM(CASE WHEN iqa_status = 'PASS' THEN 1 ELSE 0 END) as iqa_pass,
                SUM(CASE WHEN iqa_status = 'FAIL' THEN 1 ELSE 0 END) as iqa_fail,
                SUM(CASE WHEN fraud_status = 'CLEAR' THEN 1 ELSE 0 END) as fraud_clear,
                SUM(CASE WHEN fraud_status != 'CLEAR' THEN 1 ELSE 0 END) as fraud_flagged,
                SUM(CASE WHEN status = 'SUBMITTED' THEN 1 ELSE 0 END) as submitted
            ")
            ->whereDate('created_at', $date)
            ->groupBy('grid_code')
            ->get();

        return response()->json($summary);
    }

    public function branchSummary(Request $request): JsonResponse
    {
        $date = $request->date ?? today()->toDateString();

        $summary = DB::table('cts_instruments')
            ->selectRaw("
                branch_code,
                COUNT(*) as total,
                SUM(amount_figures) as total_amount,
                SUM(CASE WHEN status = 'SUBMITTED' THEN 1 ELSE 0 END) as submitted,
                SUM(CASE WHEN status = 'HOLD' THEN 1 ELSE 0 END) as on_hold,
                SUM(CASE WHEN is_high_value = 1 THEN 1 ELSE 0 END) as high_value_count
            ")
            ->when($request->grid_code, fn($q) => $q->where('grid_code', $request->grid_code))
            ->when($request->region_code, fn($q) => $q->where('region_code', $request->region_code))
            ->whereDate('created_at', $date)
            ->groupBy('branch_code')
            ->orderByDesc('total')
            ->paginate(50);

        return response()->json($summary);
    }

    public function processingStages(Request $request): JsonResponse
    {
        $date = $request->date ?? today()->toDateString();

        $stages = DB::table('cts_instruments')
            ->selectRaw("status, COUNT(*) as count, SUM(amount_figures) as total_amount")
            ->whereDate('created_at', $date)
            ->groupBy('status')
            ->orderByRaw("FIELD(status, 'SCANNED','IQA_FAILED','DATA_ENTERED','VERIFIED','SIGNED','SUBMITTED','RETURNED','ARCHIVED')")
            ->get();

        return response()->json([
            'date'   => $date,
            'stages' => $stages,
            'flow'   => 'SCANNED → DATA_ENTRY → VERIFIED → SIGNED → SUBMITTED',
        ]);
    }

    private function outwardSummary(string $date): array
    {
        $row = DB::table('cts_instruments')
            ->where('clearing_type', 'CTS')
            ->whereDate('created_at', $date)
            ->selectRaw("COUNT(*) as total, SUM(amount_figures) as total_amount,
                         SUM(CASE WHEN status='SUBMITTED' THEN 1 ELSE 0 END) as submitted,
                         SUM(CASE WHEN status='SCANNED' THEN 1 ELSE 0 END) as pending")
            ->first();
        return (array) $row;
    }

    private function inwardSummary(string $date): array
    {
        $row = DB::table('cts_instruments')
            ->where('session_id', '!=', null)
            ->whereDate('created_at', $date)
            ->selectRaw("COUNT(*) as total, SUM(amount_figures) as total_amount,
                         SUM(CASE WHEN account_validated=1 THEN 1 ELSE 0 END) as validated")
            ->first();
        return (array) $row;
    }

    private function returnSummary(string $date): array
    {
        $row = DB::table('cts_return_instruments')
            ->whereDate('return_date', $date)
            ->selectRaw("COUNT(*) as total, SUM(amount) as total_amount,
                         SUM(CASE WHEN status='SUBMITTED' THEN 1 ELSE 0 END) as submitted")
            ->first();
        return (array) $row;
    }

    private function fraudSummary(string $date): array
    {
        $row = DB::table('cts_fraud_alerts')
            ->whereDate('created_at', $date)
            ->selectRaw("COUNT(*) as total,
                         SUM(CASE WHEN severity='HIGH' THEN 1 ELSE 0 END) as high,
                         SUM(CASE WHEN severity='MEDIUM' THEN 1 ELSE 0 END) as medium,
                         SUM(CASE WHEN auto_blocked=1 THEN 1 ELSE 0 END) as blocked,
                         SUM(CASE WHEN status='OPEN' THEN 1 ELSE 0 END) as open")
            ->first();
        return (array) $row;
    }

    private function sessionStatus(string $date): array
    {
        return DB::table('cts_clearing_sessions')
            ->whereDate('session_date', $date)
            ->select('session_number', 'status', 'total_instruments', 'total_outward_amount', 'submitted_at')
            ->get()
            ->toArray();
    }

    private function iqaSummary(string $date): array
    {
        $row = DB::table('cts_instruments')
            ->whereDate('created_at', $date)
            ->selectRaw("COUNT(*) as total,
                         SUM(CASE WHEN iqa_status='PASS' THEN 1 ELSE 0 END) as pass,
                         SUM(CASE WHEN iqa_status='FAIL' THEN 1 ELSE 0 END) as fail")
            ->first();
        $total = (int) ($row->total ?? 0);
        $pass  = (int) ($row->pass  ?? 0);
        return [
            'total'       => $total,
            'pass'        => $pass,
            'fail'        => (int) ($row->fail ?? 0),
            'pass_percent'=> $total > 0 ? round(($pass / $total) * 100, 2) : 0,
            'p2f_target'  => 'Near Zero (per RBI)',
        ];
    }

    private function slaStatus(): array
    {
        return [
            'uptime_target'  => config('cts.sla.uptime_percent'),
            'rto_target_min' => config('cts.sla.rto_minutes'),
            'rpo_target_min' => config('cts.sla.rpo_minutes'),
        ];
    }
}
