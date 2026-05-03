<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\MICRService;
use App\Services\IQAService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProcessBulkUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 300;

    public function __construct(
        private string $filePath,
        private int    $batchId,
        private int    $userId
    ) {}

    public function handle(MICRService $micr): void
    {
        $rows = Excel::toArray([], storage_path("app/{$this->filePath}"))[0];

        $processed = 0;
        $errors    = [];

        DB::transaction(function () use ($rows, $micr, &$processed, &$errors) {
            foreach (array_slice($rows, 1) as $index => $row) {
                try {
                    $micrData  = $micr->parse($row[0] ?? '');
                    $validated = $micr->validate($micrData);

                    DB::table('cts_instruments')->insert([
                        'instrument_id'  => \Str::uuid(),
                        'batch_id'       => $this->batchId,
                        'cheque_number'  => $micrData['cheque_number'],
                        'micr_code'      => $micrData['full_micr'],
                        'bank_sort_code' => $micrData['bank_sort_code'],
                        'account_number' => $micrData['account_number'],
                        'amount_figures' => $row[1] ?? 0,
                        'payee_name'     => $row[2] ?? '',
                        'instrument_date'=> $row[3] ?? now()->toDateString(),
                        'status'         => $validated['valid'] ? 'SCANNED' : 'VALIDATION_ERROR',
                        'clearing_type'  => 'CTS',
                        'processed_by'   => $this->userId,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ]);

                    $processed++;
                } catch (\Exception $e) {
                    $errors[] = "Row {$index}: " . $e->getMessage();
                    Log::error("Bulk upload row error", ['row' => $index, 'error' => $e->getMessage()]);
                }
            }
        });

        Log::info("Bulk upload complete", [
            'batch_id'  => $this->batchId,
            'processed' => $processed,
            'errors'    => count($errors),
        ]);
    }
}
