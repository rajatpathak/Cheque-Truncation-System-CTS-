<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Instrument;
use App\Services\OCRService;
use Illuminate\Support\Facades\Log;

class RunOCRExtraction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(private int $instrumentId) {}

    public function handle(OCRService $ocr): void
    {
        $instrument = Instrument::findOrFail($this->instrumentId);

        if ($instrument->iqa_status !== 'PASS') {
            Log::warning("Skipping OCR for IQA-failed instrument {$instrument->instrument_id}");
            return;
        }

        $result = $ocr->extract($instrument);

        if ($result['status'] === 'SUCCESS') {
            Log::info("OCR extraction completed for {$instrument->instrument_id}");
        } else {
            Log::error("OCR extraction failed for {$instrument->instrument_id}", $result);
            $this->release(60); // retry in 60 seconds
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("OCR job permanently failed for instrument {$this->instrumentId}", [
            'exception' => $e->getMessage(),
        ]);
    }
}
