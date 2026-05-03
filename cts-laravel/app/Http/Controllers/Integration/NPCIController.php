<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class NPCIController extends Controller
{
    private string $npciUrl;

    public function __construct()
    {
        $this->npciUrl = config('cts.npci.base_url');
    }

    public function submit(Request $request): JsonResponse
    {
        $request->validate([
            'session_id'    => 'required|exists:cts_clearing_sessions,id',
            'grid_code'     => 'required|string',
        ]);

        $response = Http::withHeaders($this->headers())
            ->post("{$this->npciUrl}/clearing/submit", $request->all());

        return response()->json($response->json(), $response->status());
    }

    public function status(Request $request, string $batchRef): JsonResponse
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->npciUrl}/clearing/status/{$batchRef}");

        return response()->json($response->json());
    }

    /**
     * Register/trigger continuous clearing for the grid.
     * Per RBI mandate for CTS National Grid.
     */
    public function continuousClearing(Request $request): JsonResponse
    {
        $request->validate(['grid_code' => 'required|string']);

        $response = Http::withHeaders($this->headers())
            ->post("{$this->npciUrl}/clearing/continuous", [
                'grid_code'  => $request->grid_code,
                'bank_code'  => config('cts.bank.ifsc_prefix'),
                'initiated_at' => now()->toIso8601String(),
            ]);

        return response()->json($response->json());
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . cache()->get('npci_access_token'),
            'X-Bank-Code'   => config('cts.bank.ifsc_prefix'),
            'Content-Type'  => 'application/json',
        ];
    }
}
