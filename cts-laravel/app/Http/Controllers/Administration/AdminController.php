<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Services\NotificationService;

class AdminController extends Controller
{
    public function __construct(private NotificationService $notify) {}

    public function parameters(Request $request): JsonResponse
    {
        $params = DB::table('cts_parameters')->get();
        return response()->json($params);
    }

    public function updateParameter(Request $request, string $key): JsonResponse
    {
        $request->validate(['value' => 'required']);

        $before = DB::table('cts_parameters')->where('key', $key)->value('value');

        DB::table('cts_parameters')->updateOrInsert(
            ['key' => $key],
            ['value' => $request->value, 'updated_by' => $request->user()->id, 'updated_at' => now()]
        );

        activity()->withProperties(['key' => $key, 'before' => $before, 'after' => $request->value])
                  ->log('PARAMETER_UPDATED');

        Cache::flush(); // Clear all cached config values

        return response()->json(['status' => 'UPDATED', 'key' => $key, 'value' => $request->value]);
    }

    /**
     * Run End-of-Day processing:
     * - Raise hold-back exceptions
     * - Disable all users
     * - Generate EOD reports
     * - Trigger replication sync check
     */
    public function runEOD(Request $request): JsonResponse
    {
        dispatch(new \App\Jobs\RunEndOfDay(
            now()->toDateString(),
            $request->user()->id
        ));

        return response()->json([
            'status'  => 'EOD_QUEUED',
            'date'    => now()->toDateString(),
            'message' => 'EOD job dispatched. Users will be disabled after processing.',
        ]);
    }

    /**
     * Disable all non-admin users at close of day (RBI mandate).
     */
    public function disableAllUsers(Request $request): JsonResponse
    {
        $count = DB::table('users')
            ->where('role', '!=', 'admin')
            ->update(['is_active' => false, 'disabled_at' => now()]);

        activity()->log("EOD: {$count} users disabled by {$request->user()->name}");
        $this->notify->notifyITTeam("CTS: All {$count} users disabled for EOD processing.");

        return response()->json(['status' => 'USERS_DISABLED', 'count' => $count]);
    }

    /**
     * Enable users at start of day.
     */
    public function enableUsers(Request $request): JsonResponse
    {
        $count = DB::table('users')
            ->where('role', '!=', 'admin')
            ->update(['is_active' => true, 'disabled_at' => null]);

        activity()->log("BOD: {$count} users enabled by {$request->user()->name}");

        return response()->json(['status' => 'USERS_ENABLED', 'count' => $count]);
    }

    /**
     * Monthly user access review report (as per RBI IS requirements).
     */
    public function monthlyAccessReview(Request $request): JsonResponse
    {
        $month  = $request->month ?? now()->format('Y-m');
        $report = DB::table('users')
            ->select('id', 'name', 'username', 'branch_code', 'roles', 'last_login_at', 'is_active', 'daily_cheque_limit')
            ->get()
            ->map(function ($user) use ($month) {
                $user->last_month_logins = DB::table('cts_audit_trail')
                    ->where('user_id', $user->id)
                    ->where('action', 'LIKE', '%login%')
                    ->whereRaw("DATE_FORMAT(timestamp, '%Y-%m') = ?", [$month])
                    ->count();
                return $user;
            });

        return response()->json([
            'month'   => $month,
            'total'   => $report->count(),
            'active'  => $report->where('is_active', true)->count(),
            'users'   => $report,
        ]);
    }

    public function patchCompliance(Request $request): JsonResponse
    {
        $patches = DB::table('cts_patch_log')
            ->orderByDesc('applied_at')
            ->get();

        $overdue = $patches->where('status', 'PENDING')
                           ->where('due_date', '<', now()->toDateString());

        return response()->json([
            'total_patches'   => $patches->count(),
            'applied'         => $patches->where('status', 'APPLIED')->count(),
            'pending'         => $patches->where('status', 'PENDING')->count(),
            'overdue'         => $overdue->count(),
            'patches'         => $patches,
        ]);
    }

    public function applyPatch(Request $request, int $id): JsonResponse
    {
        DB::table('cts_patch_log')->where('id', $id)->update([
            'status'     => 'APPLIED',
            'applied_at' => now(),
            'applied_by' => $request->user()->id,
        ]);

        return response()->json(['status' => 'PATCH_APPLIED']);
    }
}
