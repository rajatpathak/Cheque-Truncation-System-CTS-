<?php

namespace App\Http\Controllers\InwardClearing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\ClearingSession;
use App\Models\Instrument;
use App\Services\CHIDEMService;
use Illuminate\Support\Str;

class InwardController extends Controller
{
    public function __construct(private CHIDEMService $chidem) {}

    public function sessions(Request $request): JsonResponse
    {
        $sessions = ClearingSession::where('session_type', 'INWARD')
            ->when($request->date,   fn($q) => $q->whereDate('session_date', $request->date))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json($sessions);
    }

    public function createSession(Request $request): JsonResponse
    {
        $request->validate([
            'session_date'        => 'required|date',
            'clearing_type'       => 'required|in:CTS,NONCTS,SPECIAL',
            'grid_code'           => 'required|string',
            'is_continuous'       => 'boolean',
        ]);

        // Pull inward data from CHI/DEM
        $chiData = $this->chidem->receiveInward($request->grid_code, $request->session_date);

        $session = ClearingSession::create([
            'session_number'       => 'IN-' . Str::upper(Str::random(8)),
            'session_date'         => $request->session_date,
            'session_type'         => 'INWARD',
            'clearing_type'        => $request->clearing_type,
            'grid_code'            => $request->grid_code,
            'chi_session_ref'      => $chiData['data']['chi_session_ref'] ?? null,
            'status'               => 'OPEN',
            'opened_by'            => $request->user()->id,
            'is_continuous_clearing' => $request->is_continuous ?? false,
        ]);

        // Import instruments from CHI/DEM response
        if (!empty($chiData['data']['instruments'])) {
            $this->importInstruments($chiData['data']['instruments'], $session->id);
        }

        return response()->json([
            'session'           => $session,
            'instruments_count' => count($chiData['data']['instruments'] ?? []),
        ], 201);
    }

    public function sessionInstruments(Request $request, int $id): JsonResponse
    {
        $session = ClearingSession::findOrFail($id);
        $instruments = Instrument::where('session_id', $session->id)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->paginate(50);

        return response()->json($instruments);
    }

    public function submitSession(Request $request, int $id): JsonResponse
    {
        $session = ClearingSession::findOrFail($id);

        $session->update([
            'status'      => 'PROCESSING',
            'submitted_at'=> now(),
        ]);

        // Dispatch CBS upload
        dispatch(new \App\Jobs\UploadInwardToCBS($session->id));

        return response()->json(['status' => 'SESSION_SUBMITTED', 'session_id' => $session->id]);
    }

    private function importInstruments(array $instruments, int $sessionId): void
    {
        foreach ($instruments as $item) {
            Instrument::create([
                'instrument_id'   => Str::uuid(),
                'session_id'      => $sessionId,
                'cheque_number'   => $item['cheque_number'],
                'micr_code'       => $item['micr_code'],
                'bank_sort_code'  => $item['bank_sort_code'],
                'account_number'  => $item['account_number'],
                'amount_figures'  => $item['amount'],
                'payee_name'      => $item['payee_name'] ?? null,
                'instrument_date' => $item['instrument_date'],
                'image_hash_grey' => $item['image_grey_hash'] ?? null,
                'iqa_status'      => $item['iqa_status'] ?? 'PASS',
                'clearing_type'   => 'CTS',
                'status'          => 'SCANNED',
                'fraud_status'    => 'PENDING',
                'signature_status'=> 'SIGNED',
            ]);
        }
    }
}
