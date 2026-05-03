<?php

namespace App\Http\Controllers\FraudDetection;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Instrument;
use App\Models\FraudAlert;
use App\Services\FraudDetectionService;
use Illuminate\Support\Facades\DB;

class FraudDetectionController extends Controller
{
    public function __construct(private FraudDetectionService $fraud) {}

    public function scanInstrument(Request $request, string $instrumentId): JsonResponse
    {
        $instrument = Instrument::where('instrument_id', $instrumentId)->firstOrFail();
        $result     = $this->fraud->scan($instrument);
        return response()->json($result);
    }

    public function alerts(Request $request): JsonResponse
    {
        $alerts = FraudAlert::with('instrument')
            ->when($request->severity, fn($q) => $q->where('severity', $request->severity))
            ->when($request->status,   fn($q) => $q->where('status', $request->status))
            ->when($request->date,     fn($q) => $q->whereDate('created_at', $request->date))
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json($alerts);
    }

    public function alert(Request $request, int $id): JsonResponse
    {
        return response()->json(FraudAlert::with('instrument')->findOrFail($id));
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        $request->validate(['resolution_notes' => 'required|string|max:1000']);

        FraudAlert::findOrFail($id)->update([
            'status'           => 'RESOLVED',
            'resolved_by'      => $request->user()->id,
            'resolved_at'      => now(),
            'resolution_notes' => $request->resolution_notes,
        ]);

        return response()->json(['status' => 'RESOLVED']);
    }

    public function blacklist(Request $request): JsonResponse
    {
        return response()->json(
            DB::table('cts_blacklisted_accounts')->where('active', true)->paginate(30)
        );
    }

    public function addToBlacklist(Request $request): JsonResponse
    {
        $request->validate([
            'account_number' => 'required|string|max:20',
            'account_name'   => 'nullable|string|max:200',
            'reason'         => 'required|string|max:300',
            'bank_sort_code' => 'nullable|string|size:9',
        ]);

        DB::table('cts_blacklisted_accounts')->updateOrInsert(
            ['account_number' => $request->account_number],
            [
                'account_name'    => $request->account_name,
                'bank_sort_code'  => $request->bank_sort_code,
                'reason'          => $request->reason,
                'blacklisted_by'  => $request->user()->name,
                'active'          => true,
                'blacklisted_date'=> today()->toDateString(),
                'updated_at'      => now(),
            ]
        );

        return response()->json(['status' => 'BLACKLISTED', 'account' => $request->account_number], 201);
    }

    public function removeFromBlacklist(Request $request, string $accountNo): JsonResponse
    {
        DB::table('cts_blacklisted_accounts')
          ->where('account_number', $accountNo)
          ->update(['active' => false, 'updated_at' => now()]);

        activity()->log("Account {$accountNo} removed from blacklist by {$request->user()->name}");

        return response()->json(['status' => 'REMOVED_FROM_BLACKLIST']);
    }

    public function suspiciousInstruments(Request $request): JsonResponse
    {
        $instruments = Instrument::where('fraud_status', 'SUSPICIOUS')
            ->with('auditTrails')
            ->orderByDesc('created_at')
            ->paginate(25);

        return response()->json($instruments);
    }

    public function cts2010ComplianceReport(Request $request): JsonResponse
    {
        $date  = $request->date ?? today()->toDateString();
        $total = Instrument::whereDate('created_at', $date)->count();
        $compliant   = Instrument::whereDate('created_at', $date)->where('cts2010_compliant', true)->count();
        $nonCompliant = Instrument::whereDate('created_at', $date)->where('cts2010_compliant', false)->count();

        return response()->json([
            'date'          => $date,
            'total'         => $total,
            'cts2010_pass'  => $compliant,
            'cts2010_fail'  => $nonCompliant,
            'compliance_pct'=> $total > 0 ? round(($compliant / $total) * 100, 2) : 0,
            'non_cts_count' => Instrument::whereDate('created_at', $date)->where('clearing_type', 'NONCTS')->count(),
        ]);
    }
}
