<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\ClearingSession;
use App\Services\CHIDEMService;

class CHIDEMController extends Controller
{
    public function __construct(private CHIDEMService $chidem) {}

    public function submit(Request $request, int $sessionId): JsonResponse
    {
        $session = ClearingSession::findOrFail($sessionId);
        $result  = $this->chidem->submit($session);
        return response()->json($result, $result['status'] === 'SUCCESS' ? 200 : 503);
    }

    public function status(Request $request, string $ref): JsonResponse
    {
        // Poll CHI/DEM for submission status
        $response = \Http::withOptions([
            'cert'    => config('cts.chi_dem.cert'),
            'ssl_key' => config('cts.chi_dem.key'),
        ])->get(config('cts.chi_dem.host') . "/api/v2/status/{$ref}");

        return response()->json($response->json());
    }

    public function receive(Request $request): JsonResponse
    {
        $request->validate(['grid_code' => 'required', 'session_date' => 'required|date']);
        $result = $this->chidem->receiveInward($request->grid_code, $request->session_date);
        return response()->json($result);
    }

    public function rejections(Request $request): JsonResponse
    {
        $request->validate(['chi_reference' => 'required|string']);
        $rejections = $this->chidem->getRejections($request->chi_reference);
        return response()->json(['rejections' => $rejections, 'count' => count($rejections)]);
    }
}
