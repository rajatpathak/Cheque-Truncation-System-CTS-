<?php

namespace App\Services;

use App\Models\ClearingSession;
use App\Models\Instrument;
use App\Services\PKISignatureService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CHIDEMService
{
    private PKISignatureService $pki;
    private string $chiHost;

    public function __construct(PKISignatureService $pki)
    {
        $this->pki     = $pki;
        $this->chiHost = config('cts.chi_dem.host');
    }

    /**
     * Build and submit a clearing file to CHI/DEM for a session.
     * File format: NPCI-specified with digital signatures at instrument and file level.
     */
    public function submit(ClearingSession $session): array
    {
        $clearingFile = $this->buildClearingFile($session);
        $filePath     = $this->writeClearingFile($clearingFile, $session);
        $signedPath   = $this->pki->signClearingFile($filePath);

        $response = Http::withOptions([
            'cert'    => config('cts.chi_dem.cert'),
            'ssl_key' => config('cts.chi_dem.key'),
            'verify'  => true,
            'timeout' => config('cts.chi_dem.timeout'),
        ])->attach('file', file_get_contents($filePath), basename($filePath))
          ->attach('signature', file_get_contents($signedPath), basename($signedPath))
          ->post("{$this->chiHost}/api/v2/clearing/submit", [
              'session_number' => $session->session_number,
              'grid_code'      => $session->grid_code,
              'bank_code'      => config('cts.bank.ifsc_prefix'),
              'clearing_type'  => $session->clearing_type,
              'session_date'   => $session->session_date,
          ]);

        if ($response->successful()) {
            $result = $response->json();
            $session->update([
                'chi_session_ref'    => $result['chi_reference'],
                'submission_file_path' => $filePath,
                'submission_signed'  => true,
                'submitted_at'       => now(),
                'status'             => 'SUBMITTED',
            ]);

            Log::info('CHI/DEM submission successful', [
                'session' => $session->session_number,
                'chi_ref' => $result['chi_reference'],
            ]);

            return ['status' => 'SUCCESS', 'chi_reference' => $result['chi_reference']];
        }

        Log::error('CHI/DEM submission failed', ['session' => $session->session_number, 'response' => $response->body()]);
        return ['status' => 'FAILED', 'error' => $response->body()];
    }

    /**
     * Pull inward instruments from CHI/DEM for processing.
     */
    public function receiveInward(string $gridCode, string $sessionDate): array
    {
        $response = Http::withOptions([
            'cert'    => config('cts.chi_dem.cert'),
            'ssl_key' => config('cts.chi_dem.key'),
        ])->get("{$this->chiHost}/api/v2/clearing/inward", [
            'grid_code'    => $gridCode,
            'session_date' => $sessionDate,
            'bank_code'    => config('cts.bank.ifsc_prefix'),
        ]);

        return $response->successful()
            ? ['status' => 'SUCCESS', 'data' => $response->json()]
            : ['status' => 'FAILED', 'error' => $response->body()];
    }

    /**
     * Fetch CHI/DEM rejections for a session.
     */
    public function getRejections(string $chiReference): array
    {
        $response = Http::withOptions([
            'cert'    => config('cts.chi_dem.cert'),
            'ssl_key' => config('cts.chi_dem.key'),
        ])->get("{$this->chiHost}/api/v2/clearing/rejections/{$chiReference}");

        return $response->successful()
            ? $response->json()
            : [];
    }

    private function buildClearingFile(ClearingSession $session): array
    {
        $instruments = Instrument::where('session_id', $session->id)
            ->where('status', 'SIGNED')
            ->where('fraud_status', 'CLEAR')
            ->get();

        return [
            'header' => [
                'session_number'   => $session->session_number,
                'clearing_type'    => $session->clearing_type,
                'bank_sort_code'   => config('cts.bank.ifsc_prefix'),
                'grid_code'        => $session->grid_code,
                'session_date'     => $session->session_date->format('Ymd'),
                'total_items'      => $instruments->count(),
                'total_amount'     => $instruments->sum('amount_figures'),
                'created_at'       => now()->format('YmdHis'),
            ],
            'instruments' => $instruments->map(fn($i) => [
                'item_sequence_number' => $i->item_sequence_number,
                'cheque_number'        => $i->cheque_number,
                'micr_code'            => $i->micr_code,
                'bank_sort_code'       => $i->bank_sort_code,
                'account_number'       => $i->account_number,
                'amount'               => $i->amount_figures,
                'payee_name'           => $i->payee_name,
                'instrument_date'      => $i->instrument_date?->format('Ymd'),
                'image_grey_hash'      => $i->image_hash_grey,
                'image_bw_hash'        => $i->image_hash_bw,
                'image_uv_hash'        => $i->image_hash_uv,
                'digital_signature'    => $i->digital_signature,
                'iqa_status'           => $i->iqa_status,
            ])->toArray(),
        ];
    }

    private function writeClearingFile(array $data, ClearingSession $session): string
    {
        $filename = "clearing_{$session->session_number}_{$session->session_date}.json";
        $path     = storage_path("app/clearing/{$filename}");
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
        return $path;
    }
}
