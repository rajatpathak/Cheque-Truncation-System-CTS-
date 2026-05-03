<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * CTS Scheduled Commands — all times in IST (UTC+5:30).
     */
    protected function schedule(Schedule $schedule): void
    {
        // Uptime monitoring — every minute
        $schedule->call(function () {
            $status = app(\App\Services\ReplicationMonitorService::class)->getStatus();
            \DB::table('cts_uptime_log')->insert([
                'node'        => 'dc',
                'status'      => $status['dc_status'] ?? 'DOWN',
                'check_type'  => 'SCHEDULED',
                'recorded_at' => now(),
            ]);
            \DB::table('cts_uptime_log')->insert([
                'node'        => 'dr',
                'status'      => $status['dr_status'] ?? 'DOWN',
                'check_type'  => 'SCHEDULED',
                'recorded_at' => now(),
            ]);
        })->everyMinute()->name('uptime-monitor')->withoutOverlapping();

        // Expire pending approvals older than 24 hours
        $schedule->call(function () {
            \App\Models\PendingApproval::where('status', 'PENDING')
                ->where('expires_at', '<', now())
                ->update(['status' => 'EXPIRED']);
        })->hourly()->name('expire-approvals');

        // EOD processing — trigger at 6:00 PM IST (configurable)
        $schedule->job(new \App\Jobs\RunEndOfDay(now()->toDateString(), 1))
                 ->dailyAt('18:00')
                 ->timezone('Asia/Kolkata')
                 ->name('eod-processing')
                 ->withoutOverlapping();

        // Enable users at start of business — 8:00 AM IST
        $schedule->call(function () {
            \DB::table('users')
               ->where('role', '!=', 'admin')
               ->update(['is_active' => true, 'disabled_at' => null]);
        })->dailyAt('08:00')->timezone('Asia/Kolkata')->name('enable-users-bod');

        // EOD report generation — 7:00 PM IST
        $schedule->job(new \App\Jobs\GenerateScheduledReports(now()->toDateString(), 'EOD'))
                 ->dailyAt('19:00')
                 ->timezone('Asia/Kolkata')
                 ->name('eod-reports');

        // Monthly report — 1st of each month at 7:00 AM
        $schedule->job(new \App\Jobs\GenerateScheduledReports(now()->toDateString(), 'MONTHLY'))
                 ->monthlyOn(1, '07:00')
                 ->timezone('Asia/Kolkata')
                 ->name('monthly-reports');

        // Sync Finacle masters — nightly at 2:00 AM IST
        $schedule->command('cts:sync-finacle-masters')
                 ->dailyAt('02:00')
                 ->timezone('Asia/Kolkata')
                 ->name('sync-finacle-masters');

        // Archive old instruments — weekly on Sunday 3:00 AM
        $schedule->call(function () {
            \App\Models\Instrument::where('status', 'SUBMITTED')
                ->where('is_archived', false)
                ->whereDate('created_at', '<', now()->subYears(10))
                ->update(['is_archived' => true, 'archived_at' => now()]);
        })->weekly()->sundays()->at('03:00')->timezone('Asia/Kolkata')->name('archive-old-instruments');

        // Purge expired uptime logs > 2 years
        $schedule->call(function () {
            \DB::table('cts_uptime_log')
               ->where('recorded_at', '<', now()->subYears(2))
               ->delete();
        })->monthly()->name('purge-uptime-logs');
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
