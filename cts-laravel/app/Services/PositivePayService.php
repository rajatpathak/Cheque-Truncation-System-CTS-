<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PositivePayService
{
    private string $npciCPPSUrl;

    public function __construct()
    {
        $this->npciCPPSUrl = config('cts.npci.base_url') . '/cpps';
    }

    /**
     * Check a cheque against NPCI Centralised Positive Pay System (CPPS).
     * Implemented w.e.f. 01.01.2021 as per RBI mandate.
     */
    public function check(array $chequeData): array
    {
        $payload = [
            'account_number'  => $chequeData['account_number'],
            'cheque_number'   => $chequeData['cheque_number'],
            'amount'          => $chequeData['amount'],
            'date'            => $chequeData['instrument_date'],
            'payee_name'      => $chequeData['payee_name'],
            'bank_sort_code'  => $chequeData['bank_sort_code'],
            'requesting_bank' => config('cts.bank.ifsc_prefix'),
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getNPCIToken(),
                'Content-Type'  => 'application/json',
            ])->timeout(15)->post("{$this->npciCPPSUrl}/verify", $payload);

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'status'   => $result['match_status'] === 'MATCH' ? 'VERIFIED' : 'FAILED',
                    'response' => $result,
                    'ref'      => $result['transaction_ref'] ?? null,
                ];
            }

            Log::warning('CPPS check failed', ['payload' => $payload, 'response' => $response->body()]);
            return ['status' => 'UNVERIFIED', 'response' => null, 'ref' => null];
        } catch (\Exception $e) {
            Log::error('CPPS exception', ['error' => $e->getMessage()]);
            return ['status' => 'ERROR', 'response' => null, 'ref' => null];
        }
    }

    /**
     * Register a new positive pay instruction (from Omnichannel banking / branch).
     */
    public function register(array $instruction): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->getNPCIToken(),
        ])->post("{$this->npciCPPSUrl}/register", $instruction);

        return [
            'success' => $response->successful(),
            'ref'     => $response->json('registration_ref'),
        ];
    }

    /**
     * Send high-value cheque pre-authorization alert to account holder.
     */
    public function sendHighValueAlert(array $chequeData, string $channel = 'SMS'): bool
    {
        $message = "Alert: A high-value cheque no {$chequeData['cheque_number']} "
                 . "for Rs. " . number_format($chequeData['amount'], 2)
                 . " has been presented for payment on {$chequeData['instrument_date']}. "
                 . "Contact your branch to report discrepancy. Ref: {$chequeData['instrument_id']}";

        return app(NotificationService::class)->send(
            $chequeData['account_number'],
            $message,
            $channel
        );
    }

    private function getNPCIToken(): string
    {
        return cache()->remember('npci_access_token', 3500, function () {
            $response = Http::post(config('cts.npci.base_url') . '/auth/token', [
                'client_id'     => env('NPCI_CLIENT_ID'),
                'client_secret' => env('NPCI_CLIENT_SECRET'),
                'grant_type'    => 'client_credentials',
            ]);
            return $response->json('access_token');
        });
    }
}
