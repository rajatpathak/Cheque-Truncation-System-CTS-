<?php

namespace App\Http\Controllers\OutwardClearing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Instrument;
use App\Services\MICRService;

class MICRController extends Controller
{
    public function __construct(private MICRService $micr) {}

    public function read(Request $request): JsonResponse
    {
        $request->validate(['micr_data' => 'required|string|min:28|max:35']);
        $parsed = $this->micr->parse($request->micr_data);
        $bank   = $this->micr->resolveBank($parsed['bank_sort_code']);
        return response()->json(['parsed' => $parsed, 'bank_info' => $bank]);
    }

    public function validate(Request $request): JsonResponse
    {
        $request->validate(['micr_data' => 'required|string']);
        $parsed    = $this->micr->parse($request->micr_data);
        $validated = $this->micr->validate($parsed);
        return response()->json($validated);
    }

    public function correct(Request $request, string $instrumentId): JsonResponse
    {
        $request->validate([
            'corrected_micr' => 'required|string|min:28|max:35',
            'reason'         => 'required|string|max:300',
        ]);

        $instrument = Instrument::where('instrument_id', $instrumentId)->firstOrFail();
        $parsed     = $this->micr->parse($request->corrected_micr);
        $validated  = $this->micr->validate($parsed);

        if (!$validated['valid']) {
            return response()->json(['error' => 'INVALID_CORRECTED_MICR', 'errors' => $validated['errors']], 422);
        }

        $before = $instrument->only(['micr_code', 'cheque_number', 'bank_sort_code', 'account_number']);
        $this->micr->updateInstrument($instrument, $parsed);

        activity()->on($instrument)->withProperties(['before' => $before, 'reason' => $request->reason])->log('MICR_CORRECTED');

        return response()->json(['status' => 'MICR_CORRECTED', 'parsed' => $parsed]);
    }
}
