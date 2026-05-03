<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Instrument;
use App\Services\FraudDetectionService;
use Illuminate\Support\Facades\Log;

class RunFraudDetection implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 60;

    public function __construct(private int $instrumentId) {}

    public function handle(FraudDetectionService $fraudService): void
    {
        $instrument = Instrument::findOrFail($this->instrumentId);
        $result     = $fraudService->scan($instrument);

        Log::info("Fraud detection completed for {$instrument->instrument_id}", [
            'status' => $result['status'],
            'flags'  => $result['flags'],
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error("Fraud detection job failed for instrument {$this->instrumentId}", [
            'exception' => $e->getMessage(),
        ]);
    }
}
