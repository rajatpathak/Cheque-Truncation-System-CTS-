<?php

namespace App\Http\Controllers\ReturnProcessing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\ReturnInstrument;
use App\Models\Instrument;
use App\Services\PKISignatureService;
use App\Services\CHIDEMService;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{
    public function __construct(
        private PKISignatureService $pki,
        private CHIDEMService       $chidem
    ) {}

    public function index(Request $request): JsonResponse
    {
        $returns = ReturnInstrument::with('originalInstrument')
            ->when($request->type,   fn($q) => $q->where('return_type', $request->type))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->date,   fn($q) => $q->whereDate('return_date', $request->date))
            ->when($request->branch, fn($q) => $q->where('branch_code', $request->branch))
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json($returns);
    }

    /**
     * Process an inward return — instrument received via CHI/DEM that needs to be returned.
     */
    public function processInwardReturn(Request $request): JsonResponse
    {
        $request->validate([
            'instrument_id'   => 'required|string',
            'reason_code'     => 'required|string|max:10',
            'reason_desc'     => 'required|string|max:200',
            'return_date'     => 'required|date',
        ]);

        $instrument = Instrument::where('instrument_id', $request->instrument_id)->firstOrFail();

        DB::transaction(function () use ($request, $instrument) {
            $return = ReturnInstrument::create([
                'instrument_id'             => $instrument->instrument_id,
                'original_instrument_id'    => $instrument->id,
                'return_type'               => 'INWARD_RETURN',
                'return_reason_code'        => $request->reason_code,
                'return_reason_description' => $request->reason_desc,
                'return_date'               => $request->return_date,
                'clearing_date'             => $instrument->presentment_date,
                'branch_code'               => $instrument->branch_code,
                'amount'                    => $instrument->amount_figures,
                'status'                    => 'PENDING',
                'processed_by'              => $request->user()->id,
            ]);

            $instrument->update(['status' => 'RETURN_INITIATED']);
        });

        return response()->json(['status' => 'RETURN_INITIATED'], 201);
    }

    /**
     * Process outward return — CHI/DEM rejected our presented instrument.
     */
    public function processOutwardReturn(Request $request): JsonResponse
    {
        $request->validate([
            'chi_reference'   => 'required|string',
            'instrument_id'   => 'required|string',
            'rejection_reason'=> 'required|string',
        ]);

        $instrument = Instrument::where('instrument_id', $request->instrument_id)->firstOrFail();

        ReturnInstrument::create([
            'instrument_id'             => $instrument->instrument_id,
            'original_instrument_id'    => $instrument->id,
            'return_type'               => 'OUTWARD_RETURN',
            'return_reason_description' => $request->rejection_reason,
            'chi_reference'             => $request->chi_reference,
            'return_date'               => now()->toDateString(),
            'branch_code'               => $instrument->branch_code,
            'amount'                    => $instrument->amount_figures,
            'status'                    => 'PENDING',
            'processed_by'              => $request->user()->id,
        ]);

        $instrument->update(['status' => 'CHI_REJECTED']);

        return response()->json(['status' => 'OUTWARD_RETURN_INITIATED'], 201);
    }

    /**
     * Re-present a returned instrument — with corrected MICR or updated IQA override.
     */
    public function represent(Request $request, int $returnId): JsonResponse
    {
        $return = ReturnInstrument::findOrFail($returnId);

        $request->validate([
            'corrected_micr'  => 'nullable|string',
            'iqa_override'    => 'boolean',
            'remarks'         => 'nullable|string',
        ]);

        if ($return->representment_count >= 2) {
            return response()->json(['error' => 'MAX_REPRESENTMENT_EXCEEDED'], 422);
        }

        $return->update([
            'representment_count'   => $return->representment_count + 1,
            'last_representment_at' => now(),
            'micr_corrected'        => (bool) $request->corrected_micr,
            'iqa_override'          => $request->iqa_override ?? false,
            'remarks'               => $request->remarks,
            'status'                => 'PENDING',
        ]);

        if ($request->corrected_micr) {
            Instrument::where('instrument_id', $return->instrument_id)
                      ->update(['micr_code' => $request->corrected_micr]);
        }

        return response()->json(['status' => 'REPRESENTMENT_QUEUED', 'attempt' => $return->representment_count]);
    }

    /**
     * Sign inward returns digitally and submit to CHI/DEM.
     */
    public function signAndSubmit(Request $request, int $returnId): JsonResponse
    {
        $return = ReturnInstrument::with('originalInstrument')->findOrFail($returnId);

        // Sign
        $this->pki->signInstrument($return->originalInstrument);
        $return->update(['signed' => true, 'signed_by' => $request->user()->id]);

        // Submit to CHI/DEM
        $submitResult = $this->chidem->submitReturn($return);

        if ($submitResult['status'] === 'SUCCESS') {
            $return->update([
                'submitted_to_chi' => true,
                'chi_reference'    => $submitResult['chi_reference'],
                'status'           => 'SUBMITTED',
            ]);
        }

        return response()->json([
            'status'    => $submitResult['status'],
            'chi_ref'   => $submitResult['chi_reference'] ?? null,
        ]);
    }

    public function frequentReturnAccounts(Request $request): JsonResponse
    {
        $accounts = ReturnInstrument::selectRaw('account_number, COUNT(*) as return_count, SUM(amount) as total_amount')
            ->join('cts_instruments', 'cts_return_instruments.original_instrument_id', '=', 'cts_instruments.id')
            ->where('cts_return_instruments.return_date', '>=', now()->subMonths(6))
            ->groupBy('account_number')
            ->having('return_count', '>=', 3)
            ->orderByDesc('return_count')
            ->get();

        return response()->json($accounts);
    }
}
