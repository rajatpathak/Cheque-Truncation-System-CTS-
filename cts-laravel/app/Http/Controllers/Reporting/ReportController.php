<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function daily(Request $request): JsonResponse
    {
        $date = $request->date ?? today()->toDateString();
        return response()->json($this->buildClearingReport($date, $date));
    }

    public function monthly(Request $request): JsonResponse
    {
        $month = $request->month ?? now()->format('Y-m');
        return response()->json($this->buildClearingReport($month . '-01', date('Y-m-t', strtotime($month . '-01'))));
    }

    public function yearly(Request $request): JsonResponse
    {
        $year = $request->year ?? now()->year;
        return response()->json($this->buildClearingReport("{$year}-01-01", "{$year}-12-31"));
    }

    public function sessionReport(Request $request, int $id): mixed
    {
        $session = \App\Models\ClearingSession::with('batches.instruments')->findOrFail($id);

        if ($request->format === 'pdf') {
            $pdf = Pdf::loadView('reports.session', ['session' => $session]);
            return $pdf->download("session_{$session->session_number}.pdf");
        }

        return response()->json($session);
    }

    public function custom(Request $request): JsonResponse
    {
        $request->validate([
            'from_date'   => 'required|date',
            'to_date'     => 'required|date|after_or_equal:from_date',
            'report_type' => 'required|in:CLEARING,IQA,FRAUD,RETURNS,AUDIT',
        ]);

        $data = match ($request->report_type) {
            'CLEARING' => $this->buildClearingReport($request->from_date, $request->to_date),
            'IQA'      => $this->buildIQAReport($request->from_date, $request->to_date),
            'FRAUD'    => $this->buildFraudReport($request->from_date, $request->to_date),
            'RETURNS'  => $this->buildReturnReport($request->from_date, $request->to_date),
            'AUDIT'    => $this->buildAuditReport($request->from_date, $request->to_date),
        };

        return response()->json($data);
    }

    public function iqaFailures(Request $request): JsonResponse
    {
        $date = $request->date ?? today()->toDateString();
        return response()->json($this->buildIQAReport($date, $date));
    }

    public function exceptions(Request $request): JsonResponse
    {
        $date = $request->date ?? today()->toDateString();
        $data = DB::table('cts_instruments')
            ->whereDate('created_at', $date)
            ->whereIn('status', ['HOLD', 'BLOCKED', 'IQA_FAILED', 'VALIDATION_ERROR'])
            ->select('instrument_id', 'branch_code', 'status', 'hold_reason', 'fraud_status', 'iqa_failure_reasons')
            ->get();

        return response()->json(['date' => $date, 'exceptions' => $data, 'count' => $data->count()]);
    }

    public function auditTrail(Request $request): JsonResponse
    {
        $trails = DB::table('cts_audit_trail')
            ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->module,  fn($q) => $q->where('module', $request->module))
            ->when($request->from,    fn($q) => $q->where('timestamp', '>=', $request->from))
            ->when($request->to,      fn($q) => $q->where('timestamp', '<=', $request->to))
            ->orderByDesc('timestamp')
            ->paginate(50);

        return response()->json($trails);
    }

    public function returnAnalysis(Request $request): JsonResponse
    {
        $from = $request->from ?? now()->subMonth()->toDateString();
        $to   = $request->to   ?? today()->toDateString();
        return response()->json($this->buildReturnReport($from, $to));
    }

    public function scheduleReport(Request $request): JsonResponse
    {
        $request->validate([
            'report_name'       => 'required|string|max:200',
            'report_type'       => 'required|string',
            'frequency'         => 'required|in:EOD,MONTHLY,YEARLY',
            'email_recipients'  => 'nullable|string',
        ]);

        DB::table('cts_report_schedules')->insert([
            'report_name'      => $request->report_name,
            'report_type'      => $request->report_type,
            'frequency'        => $request->frequency,
            'email_recipients' => $request->email_recipients,
            'active'           => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return response()->json(['status' => 'SCHEDULE_CREATED'], 201);
    }

    public function archivedReports(Request $request): JsonResponse
    {
        $files = \Storage::files('reports');
        $list  = array_map(fn($f) => [
            'name' => basename($f),
            'size' => \Storage::size($f),
            'date' => \Storage::lastModified($f),
        ], $files);

        return response()->json($list);
    }

    private function buildClearingReport(string $from, string $to): array
    {
        return [
            'period'     => ['from' => $from, 'to' => $to],
            'outward'    => DB::table('cts_instruments')
                ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                ->selectRaw("clearing_type, COUNT(*) as count, SUM(amount_figures) as amount")
                ->groupBy('clearing_type')
                ->get(),
            'by_status'  => DB::table('cts_instruments')
                ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                ->selectRaw("status, COUNT(*) as count")
                ->groupBy('status')
                ->get(),
            'by_grid'    => DB::table('cts_instruments')
                ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                ->selectRaw("grid_code, COUNT(*) as count, SUM(amount_figures) as amount")
                ->groupBy('grid_code')
                ->get(),
        ];
    }

    private function buildIQAReport(string $from, string $to): array
    {
        return [
            'period'    => ['from' => $from, 'to' => $to],
            'summary'   => DB::table('cts_instruments')
                ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                ->selectRaw("iqa_status, COUNT(*) as count")
                ->groupBy('iqa_status')
                ->get(),
            'failures'  => DB::table('cts_instruments')
                ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                ->where('iqa_status', 'FAIL')
                ->select('instrument_id', 'branch_code', 'iqa_failure_reasons', 'created_at')
                ->orderByDesc('created_at')
                ->limit(500)
                ->get(),
        ];
    }

    private function buildFraudReport(string $from, string $to): array
    {
        return DB::table('cts_fraud_alerts')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw("alert_type, severity, COUNT(*) as count, SUM(auto_blocked) as blocked")
            ->groupBy('alert_type', 'severity')
            ->get()
            ->toArray();
    }

    private function buildReturnReport(string $from, string $to): array
    {
        return [
            'period'  => ['from' => $from, 'to' => $to],
            'summary' => DB::table('cts_return_instruments')
                ->whereBetween('return_date', [$from, $to])
                ->selectRaw("return_reason_code, return_type, COUNT(*) as count, SUM(amount) as amount")
                ->groupBy('return_reason_code', 'return_type')
                ->get(),
        ];
    }

    private function buildAuditReport(string $from, string $to): array
    {
        return DB::table('cts_audit_trail')
            ->whereBetween('timestamp', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw("module, action, COUNT(*) as count")
            ->groupBy('module', 'action')
            ->orderBy('module')
            ->get()
            ->toArray();
    }
}
