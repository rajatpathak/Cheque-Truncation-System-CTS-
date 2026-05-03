<?php

namespace App\Services;

use App\Models\Instrument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OCRService
{
    private string $aiEngineUrl;

    public function __construct()
    {
        $this->aiEngineUrl = config('services.ai_engine.url', env('AI_OCR_ENGINE_URL'));
    }

    /**
     * Extract cheque fields using AI/ICR/OCR engine.
     * Sends grey image to the AI service and returns structured fields.
     */
    public function extract(Instrument $instrument): array
    {
        $payload = [
            'instrument_id' => $instrument->instrument_id,
            'image_path'    => $instrument->image_path_grey,
            'image_uv_path' => $instrument->image_path_uv,
            'fields'        => [
                'amount_figures',
                'amount_words',
                'instrument_date',
                'payee_name',
                'account_number',
                'drawer_signature',
            ],
            'micr_hint' => [
                'cheque_number' => $instrument->cheque_number,
                'account_number'=> $instrument->account_number,
            ],
        ];

        try {
            $response = Http::timeout(30)
                            ->withHeaders(['X-CTS-Auth' => env('AI_ENGINE_SECRET')])
                            ->post("{$this->aiEngineUrl}/extract", $payload);

            if ($response->successful()) {
                $data = $response->json();
                $this->saveExtracted($instrument, $data);
                return ['status' => 'SUCCESS', 'data' => $data];
            }

            Log::error('OCR engine failed', ['instrument' => $instrument->instrument_id, 'response' => $response->body()]);
            return ['status' => 'FAILED', 'data' => []];
        } catch (\Exception $e) {
            Log::error('OCR engine exception', ['exception' => $e->getMessage()]);
            return ['status' => 'ERROR', 'data' => []];
        }
    }

    /**
     * Extract just the relevant snippet region for data entry display.
     */
    public function snippet(Instrument $instrument, string $field): string
    {
        $regions = [
            'amount_figures' => ['x' => 750, 'y' => 120, 'w' => 300, 'h' => 60],
            'amount_words'   => ['x' => 50,  'y' => 140, 'w' => 700, 'h' => 50],
            'payee_name'     => ['x' => 50,  'y' => 80,  'w' => 600, 'h' => 50],
            'date'           => ['x' => 700, 'y' => 40,  'w' => 300, 'h' => 50],
        ];

        $region  = $regions[$field] ?? ['x' => 0, 'y' => 0, 'w' => 200, 'h' => 80];
        $outPath = storage_path("app/snippets/{$instrument->instrument_id}_{$field}.jpg");

        if (!file_exists($outPath)) {
            $imagick = new \Imagick($instrument->image_path_grey);
            $imagick->cropImage($region['w'], $region['h'], $region['x'], $region['y']);
            $imagick->writeImage($outPath);
        }

        return $outPath;
    }

    private function saveExtracted(Instrument $instrument, array $data): void
    {
        $instrument->update([
            'amount_figures'  => $data['amount_figures'] ?? $instrument->amount_figures,
            'amount_words'    => $data['amount_words']   ?? $instrument->amount_words,
            'payee_name'      => $data['payee_name']     ?? $instrument->payee_name,
            'instrument_date' => $data['date']           ?? $instrument->instrument_date,
            'ocr_data'        => $data,
        ]);
    }
}
