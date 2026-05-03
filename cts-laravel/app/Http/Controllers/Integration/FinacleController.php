<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class FinacleController extends Controller
{
    private string $finacleHost;

    public function __construct()
    {
        $this->finacleHost = config('cts.cbs.finacle_host');
    }

    /**
     * Validate account against Finacle CBS.
     */
    public function validateAccount(Request $request): JsonResponse
    {
        $request->validate([
            'account_number' => 'required|string',
            'amount'         => 'nullable|numeric',
        ]);

        $accountNo = $request->account_number;

        $result = Cache::remember("acct_val_{$accountNo}", 300, function () use ($accountNo, $request) {
            try {
                $response = Http::timeout(config('cts.cbs.finacle_timeout'))
                    ->withHeaders(['X-Finacle-Auth' => env('FINACLE_API_KEY')])
                    ->post("{$this->finacleHost}/api/account/validate", [
                        'account_number' => $accountNo,
                        'amount'         => $request->amount,
                    ]);

                return $response->successful() ? $response->json() : ['valid' => false, 'reason' => 'CBS_ERROR'];
            } catch (\Exception $e) {
                Log::error('Finacle account validation failed', ['error' => $e->getMessage()]);
                return ['valid' => false, 'reason' => 'CBS_TIMEOUT'];
            }
        });

        return response()->json($result);
    }

    public function bulkValidate(Request $request): JsonResponse
    {
        $request->validate(['account_numbers' => 'required|array|max:500']);

        $results = [];
        foreach ($request->account_numbers as $accountNo) {
            $results[$accountNo] = Cache::remember("acct_val_{$accountNo}", 300, function () use ($accountNo) {
                try {
                    $r = Http::timeout(config('cts.cbs.finacle_timeout'))
                        ->post("{$this->finacleHost}/api/account/validate", ['account_number' => $accountNo]);
                    return $r->successful() ? $r->json() : ['valid' => false];
                } catch (\Exception $e) {
                    return ['valid' => false, 'reason' => 'CBS_TIMEOUT'];
                }
            });
        }

        return response()->json(['results' => $results, 'count' => count($results)]);
    }

    public function uploadClearingFile(Request $request): JsonResponse
    {
        $request->validate(['session_id' => 'required|exists:cts_clearing_sessions,id']);

        dispatch(new \App\Jobs\UploadInwardToCBS($request->session_id));
        return response()->json(['status' => 'UPLOAD_QUEUED']);
    }

    public function syncMasters(Request $request): JsonResponse
    {
        dispatch(new \App\Jobs\SyncFinacleMasters());
        return response()->json(['status' => 'SYNC_QUEUED', 'message' => 'Master data sync queued.']);
    }

    public function fetchSignatureMaster(Request $request): JsonResponse
    {
        $request->validate(['account_number' => 'required|string']);

        $response = Http::timeout(config('cts.cbs.finacle_timeout'))
            ->get("{$this->finacleHost}/api/signature-master/{$request->account_number}");

        return response()->json($response->successful() ? $response->json() : ['signatures' => []]);
    }
}
