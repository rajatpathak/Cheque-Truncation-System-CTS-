<?php

namespace App\Http\Controllers\InwardClearing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Instrument;
use App\Services\OCRService;
use App\Services\FraudDetectionService;
use App\Services\PositivePayService;
use App\Services\NotificationService;

class DataEntryController extends Controller
{
    public function __construct(
        private OCRService            $ocr,
        private FraudDetectionService $fraud,
        private PositivePayService    $positivePay,
        private NotificationService   $notify
    ) {}

    /**
     * Get the data entry queue for operators — shows OCR pre-filled fields.
     */
    public function queue(Request $request): JsonResponse
    {
        $instruments = Instrument::where('status', 'SCANNED')
            ->where('iqa_status', 'PASS')
            ->whereNull('amount_figures') // not yet data-entered
            ->when($request->user()->branch_code, function ($q, $branch) {
                $q->where('branch_code', $branch);
            })
            ->orderBy('created_at')
            ->paginate(20);

        // Auto-trigger OCR for each instrument in queue
        foreach ($instruments as $instrument) {
            if (!$instrument->ocr_data) {
                dispatch(new \App\Jobs\RunOCRExtraction($instrument->id));
            }
        }

        return response()->json($instruments);
    }

    /**
     * Save data entry for an instrument (Maker).
     * Validates all mandatory fields before allowing submission.
     */
    public function save(Request $request, string $instrumentId): JsonResponse
    {
        $request->validate([
            'amount_figures'  => 'required|numeric|min:0.01',
            'amount_words'    => 'required|string|max:500',
            'payee_name'      => 'required|string|max:200',
            'instrument_date' => 'required|date|before_or_equal:today',
            'account_number'  => 'required|string|min:6|max:20',
        ]);

        $instrument = Instrument::where('instrument_id', $instrumentId)
                                ->where('status', 'SCANNED')
                                ->firstOrFail();

        $instrument->update([
            'amount_figures'  => $request->amount_figures,
            'amount_words'    => $request->amount_words,
            'payee_name'      => $request->payee_name,
            'instrument_date' => $request->instrument_date,
            'account_number'  => $request->account_number,
            'processed_by'    => $request->user()->id,
            'status'          => 'DATA_ENTERED',
            'is_high_value'   => $instrument->isHighValue(),
        ]);

        // High-value alert
        if ($instrument->isHighValue() && !$instrument->high_value_alert_sent) {
            $this->positivePay->sendHighValueAlert($instrument->toArray());
            $instrument->update(['high_value_alert_sent' => true]);
        }

        // Run fraud detection asynchronously
        dispatch(new \App\Jobs\RunFraudDetection($instrument->id));

        return response()->json([
            'status'         => 'DATA_ENTERED',
            'instrument_id'  => $instrumentId,
            'is_high_value'  => $instrument->isHighValue(),
            'dual_verify'    => $instrument->requiresDualVerification(),
        ]);
    }

    /**
     * Verify data entry (Checker role).
     * Checker cannot be same user as Maker.
     */
    public function verify(Request $request, string $instrumentId): JsonResponse
    {
        $instrument = Instrument::where('instrument_id', $instrumentId)
                                ->where('status', 'DATA_ENTERED')
                                ->firstOrFail();

        if ($instrument->processed_by === $request->user()->id) {
            return response()->json(['error' => 'SELF_VERIFICATION_NOT_ALLOWED'], 403);
        }

        $instrument->update([
            'verified_by' => $request->user()->id,
            'status'      => 'VERIFIED',
        ]);

        return response()->json(['status' => 'VERIFIED', 'instrument_id' => $instrumentId]);
    }

    public function update(Request $request, string $instrumentId): JsonResponse
    {
        $instrument = Instrument::where('instrument_id', $instrumentId)
                                ->whereIn('status', ['DATA_ENTERED', 'SCANNED'])
                                ->firstOrFail();

        // Capture before value for audit trail
        $before = $instrument->only(['amount_figures', 'amount_words', 'payee_name', 'instrument_date']);

        $instrument->update($request->only([
            'amount_figures', 'amount_words', 'payee_name',
            'instrument_date', 'account_number', 'remarks',
        ]));

        activity()
            ->on($instrument)
            ->withProperties(['before' => $before, 'after' => $request->all()])
            ->log('DATA_ENTRY_UPDATED');

        return response()->json(['status' => 'UPDATED']);
    }
}
