<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;

class GenerateScheduledReports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 300;

    public function __construct(
        private string $date,
        private string $type = 'EOD'  // EOD | MONTHLY | YEARLY
    ) {}

    public function handle(NotificationService $notify): void
    {
        $schedules = DB::table('cts_report_schedules')
            ->where('active', true)
            ->where('frequency', $this->type)
            ->get();

        foreach ($schedules as $schedule) {
            try {
                $reportData = $this->generateReportData($schedule, $this->date);
                $pdfPath    = $this->exportPDF($schedule, $reportData);
                $csvPath    = $this->exportCSV($schedule, $reportData);

                // Store at configured path
                Storage::put("reports/{$schedule->report_type}/{$this->date}.pdf", file_get_contents($pdfPath));

                // Email if configured
                if ($schedule->email_recipients) {
                    foreach (explode(',', $schedule->email_recipients) as $email) {
                        \Mail::raw("Please find the {$schedule->report_name} attached.", function ($mail) use ($email, $pdfPath, $schedule) {
                            $mail->to(trim($email))
                                 ->attach($pdfPath)
                                 ->subject("[CTS] {$schedule->report_name} - " . now()->format('d-M-Y'));
                        });
                    }
                }

                Log::info("Scheduled report generated", ['report' => $schedule->report_name, 'date' => $this->date]);
            } catch (\Exception $e) {
                Log::error("Report generation failed", ['report' => $schedule->id, 'error' => $e->getMessage()]);
            }
        }
    }

    private function generateReportData(object $schedule, string $date): array
    {
        return match ($schedule->report_type) {
            'CLEARING_SUMMARY' => $this->clearingSummary($date),
            'IQA_FAILURES'     => $this->iqaFailures($date),
            'FRAUD_ALERTS'     => $this->fraudAlerts($date),
            'RETURN_ANALYSIS'  => $this->returnAnalysis($date),
            default            => [],
        };
    }

    private function clearingSummary(string $date): array
    {
        return DB::table('cts_instruments')
            ->whereDate('created_at', $date)
            ->selectRaw("clearing_type, status, COUNT(*) as count, SUM(amount_figures) as total_amount")
            ->groupBy('clearing_type', 'status')
            ->get()
            ->toArray();
    }

    private function iqaFailures(string $date): array
    {
        return DB::table('cts_instruments')
            ->whereDate('created_at', $date)
            ->where('iqa_status', 'FAIL')
            ->select('instrument_id', 'branch_code', 'grid_code', 'iqa_failure_reasons')
            ->get()
            ->toArray();
    }

    private function fraudAlerts(string $date): array
    {
        return DB::table('cts_fraud_alerts')
            ->whereDate('created_at', $date)
            ->select('instrument_id', 'alert_type', 'severity', 'status', 'auto_blocked')
            ->get()
            ->toArray();
    }

    private function returnAnalysis(string $date): array
    {
        return DB::table('cts_return_instruments')
            ->whereDate('return_date', $date)
            ->selectRaw("return_reason_code, COUNT(*) as count, SUM(amount) as total_amount")
            ->groupBy('return_reason_code')
            ->get()
            ->toArray();
    }

    private function exportPDF(object $schedule, array $data): string
    {
        $pdf  = Pdf::loadView('reports.' . strtolower($schedule->report_type), ['data' => $data, 'date' => $this->date]);
        $path = storage_path("app/reports/tmp/{$schedule->report_type}_{$this->date}.pdf");
        $pdf->save($path);
        return $path;
    }

    private function exportCSV(object $schedule, array $data): string
    {
        $path = storage_path("app/reports/tmp/{$schedule->report_type}_{$this->date}.csv");
        $fp   = fopen($path, 'w');
        if (!empty($data)) {
            fputcsv($fp, array_keys((array) $data[0]));
            foreach ($data as $row) {
                fputcsv($fp, (array) $row);
            }
        }
        fclose($fp);
        return $path;
    }
}
