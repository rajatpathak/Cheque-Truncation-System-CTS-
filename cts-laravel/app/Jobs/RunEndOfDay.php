<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Instrument;
use App\Models\ClearingSession;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RunEndOfDay implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 600;

    public function __construct(
        private string $date,
        private int    $userId
    ) {}

    public function handle(NotificationService $notify): void
    {
        Log::info("EOD processing started for {$this->date}");

        // 1. Raise hold-back exceptions — instruments not submitted
        $heldBack = Instrument::whereDate('created_at', $this->date)
            ->whereIn('status', ['SCANNED', 'DATA_ENTERED', 'VERIFIED'])
            ->where('status', '!=', 'HOLD')
            ->get();

        if ($heldBack->count() > 0) {
            Log::warning("EOD: {$heldBack->count()} instruments not submitted for {$this->date}");
            $notify->notifyITTeam(
                "EOD Alert: {$heldBack->count()} unsubmitted instruments for clearing date {$this->date}",
                'WARNING'
            );
        }

        // 2. Close all open sessions
        ClearingSession::whereDate('session_date', $this->date)
                        ->where('status', 'PROCESSING')
                        ->update(['status' => 'CLOSED', 'eod_processed' => true]);

        // 3. Disable all non-admin users
        $disabled = DB::table('users')
            ->where('role', '!=', 'admin')
            ->update(['is_active' => false, 'disabled_at' => now()]);

        // 4. Generate EOD reports asynchronously
        dispatch(new GenerateScheduledReports($this->date, 'EOD'));

        // 5. Archive submitted instruments older than retention period
        dispatch(new ArchiveInstruments($this->date));

        // 6. Log EOD completion
        DB::table('cts_eod_log')->insert([
            'date'             => $this->date,
            'held_back_count'  => $heldBack->count(),
            'users_disabled'   => $disabled,
            'processed_by'     => $this->userId,
            'completed_at'     => now(),
        ]);

        Log::info("EOD processing completed for {$this->date}", [
            'held_back'    => $heldBack->count(),
            'users_disabled' => $disabled,
        ]);
    }
}
