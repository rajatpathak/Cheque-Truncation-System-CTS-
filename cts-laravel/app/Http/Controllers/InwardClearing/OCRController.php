<?php

namespace App\Http\Controllers\InwardClearing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Instrument;
use App\Services\OCRService;

class OCRController extends Controller
{
    public function __construct(private OCRService $ocr) {}

    public function extract(Request $request, string $instrumentId): JsonResponse
    {
        $instrument = Instrument::where('instrument_id', $instrumentId)->firstOrFail();
        $result     = $this->ocr->extract($instrument);
        return response()->json($result);
    }

    public function status(Request $request, string $instrumentId): JsonResponse
    {
        $instrument = Instrument::where('instrument_id', $instrumentId)->firstOrFail();
        return response()->json([
            'instrument_id' => $instrumentId,
            'ocr_complete'  => !empty($instrument->ocr_data),
            'ocr_data'      => $instrument->ocr_data,
        ]);
    }

    public function bulkExtract(Request $request): JsonResponse
    {
        $request->validate(['session_id' => 'required|exists:cts_clearing_sessions,id']);

        $instruments = Instrument::where('session_id', $request->session_id)
                                 ->whereNull('ocr_data')
                                 ->where('iqa_status', 'PASS')
                                 ->get();

        foreach ($instruments as $instrument) {
            dispatch(new \App\Jobs\RunOCRExtraction($instrument->id));
        }

        return response()->json([
            'status'    => 'QUEUED',
            'count'     => $instruments->count(),
            'message'   => 'OCR extraction queued for all eligible instruments.',
        ]);
    }
}
