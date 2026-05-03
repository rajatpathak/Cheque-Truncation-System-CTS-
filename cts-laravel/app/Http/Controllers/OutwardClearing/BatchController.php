<?php

namespace App\Http\Controllers\OutwardClearing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Batch;
use App\Models\ClearingSession;
use App\Services\PKISignatureService;
use App\Services\CHIDEMService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BatchController extends Controller
{
    public function __construct(
        private PKISignatureService $pki,
        private CHIDEMService       $chidem
    ) {}

    public function index(Request $request): JsonResponse
    {
        $batches = Batch::query()
            ->when($request->status,      fn($q) => $q->where('status', $request->status))
            ->when($request->batch_type,  fn($q) => $q->where('batch_type', $request->batch_type))
            ->when($request->branch_code, fn($q) => $q->where('branch_code', $request->branch_code))
            ->when($request->date,        fn($q) => $q->whereDate('created_at', $request->date))
            ->with(['instruments' => fn($q) => $q->select('id', 'batch_id', 'status', 'iqa_status')])
            ->orderByDesc('created_at')
            ->paginate(25);

        return response()->json($batches);
    }

    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'batch_type'   => 'required|in:CTS,NONCTS,SPECIAL,RETURN,GOVT,P2F',
            'session_id'   => 'nullable|exists:cts_clearing_sessions,id',
            'scan_mode'    => 'nullable|in:DISTRIBUTED,CENTRALIZED',
        ]);

        $batch = Batch::create([
            'batch_number'     => $this->generateBatchNumber($request->batch_type),
            'batch_type'       => $request->batch_type,
            'branch_code'      => $request->user()->branch_code,
            'grid_code'        => $request->grid_code ?? null,
            'session_id'       => $request->session_id,
            'status'           => 'OPEN',
            'scan_mode'        => $request->scan_mode ?? 'CENTRALIZED',
            'created_by'       => $request->user()->id,
        ]);

        return response()->json($batch, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $batch = Batch::with('instruments')->findOrFail($id);
        return response()->json($batch);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $batch = Batch::where('id', $id)->where('status', 'OPEN')->firstOrFail();
        $batch->update($request->only(['batch_type', 'session_id', 'remarks']));
        return response()->json($batch);
    }

    /**
     * Close a batch — no more instruments can be added.
     */
    public function close(Request $request, int $id): JsonResponse
    {
        $batch = Batch::findOrFail($id);

        $passCount = $batch->instruments()->where('iqa_status', 'PASS')->count();
        $failCount = $batch->instruments()->where('iqa_status', 'FAIL')->count();

        $batch->update([
            'status'         => 'CLOSED',
            'closed_by'      => $request->user()->id,
            'iqa_pass_count' => $passCount,
            'iqa_fail_count' => $failCount,
            'total_amount'   => $batch->instruments()->sum('amount_figures'),
        ]);

        return response()->json([
            'status'     => 'CLOSED',
            'iqa_pass'   => $passCount,
            'iqa_fail'   => $failCount,
        ]);
    }

    /**
     * Submit batch via Maker-Checker — triggers PKI signing and CHI/DEM submission.
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        $batch = Batch::with('instruments', 'session')->findOrFail($id);

        if ($batch->status !== 'CLOSED') {
            return response()->json(['error' => 'BATCH_NOT_CLOSED'], 422);
        }

        if ($batch->instruments()->where('iqa_status', 'FAIL')->count() > 0) {
            return response()->json(['error' => 'IQA_FAILURES_PRESENT', 'message' => 'All IQA failures must be resolved or removed.'], 422);
        }

        if ($batch->instruments()->where('fraud_status', 'BLOCKED')->count() > 0) {
            return response()->json(['error' => 'BLOCKED_INSTRUMENTS_PRESENT'], 422);
        }

        DB::transaction(function () use ($batch, $request) {
            // Sign all instruments in batch
            $this->pki->signBatch($batch);

            $batch->update([
                'signed'       => true,
                'submitted_by' => $request->user()->id,
            ]);

            // Submit to CHI/DEM if session is set
            if ($batch->session) {
                $result = $this->chidem->submit($batch->session);
                if ($result['status'] === 'SUCCESS') {
                    $batch->update([
                        'status'         => 'SUBMITTED',
                        'submitted_to_chi'=> true,
                        'chi_reference'  => $result['chi_reference'],
                        'chi_submission_time' => now(),
                    ]);
                }
            } else {
                $batch->update(['status' => 'SUBMITTED']);
            }
        });

        return response()->json(['status' => 'SUBMITTED', 'batch_number' => $batch->batch_number]);
    }

    public function setType(Request $request, int $id): JsonResponse
    {
        $request->validate(['batch_type' => 'required|in:CTS,NONCTS,SPECIAL,RETURN,GOVT,P2F']);
        Batch::where('id', $id)->update(['batch_type' => $request->batch_type]);
        return response()->json(['status' => 'TYPE_UPDATED']);
    }

    private function generateBatchNumber(string $type): string
    {
        $prefix = strtoupper(substr($type, 0, 3));
        $date   = now()->format('Ymd');
        $seq    = Batch::whereDate('created_at', today())->where('batch_type', $type)->count() + 1;
        return "{$prefix}{$date}" . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}
