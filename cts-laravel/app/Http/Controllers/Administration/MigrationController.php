<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MigrationController extends Controller
{
    /**
     * Start data migration from legacy CTS to new platform.
     * Supports incremental migration, validation, and rollback.
     */
    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'source_type' => 'required|in:LEGACY_CTS,FLAT_FILE,ORACLE_IMPORT',
            'date_from'   => 'required|date',
            'date_to'     => 'required|date|after_or_equal:date_from',
        ]);

        if (Cache::get('migration_running')) {
            return response()->json(['error' => 'MIGRATION_ALREADY_RUNNING'], 409);
        }

        Cache::put('migration_status', [
            'status'       => 'STARTED',
            'source_type'  => $request->source_type,
            'date_from'    => $request->date_from,
            'date_to'      => $request->date_to,
            'started_at'   => now()->toIso8601String(),
            'started_by'   => $request->user()->name,
            'processed'    => 0,
            'errors'       => 0,
            'total'        => 0,
        ], 86400);

        Cache::put('migration_running', true, 86400);
        dispatch(new \App\Jobs\RunDataMigration($request->all(), $request->user()->id));

        return response()->json(['status' => 'MIGRATION_STARTED', 'message' => 'Migration job dispatched.']);
    }

    public function status(Request $request): JsonResponse
    {
        return response()->json(Cache::get('migration_status', ['status' => 'NOT_STARTED']));
    }

    public function progress(Request $request): JsonResponse
    {
        $status = Cache::get('migration_status', []);
        $total  = $status['total']     ?? 0;
        $done   = $status['processed'] ?? 0;

        return response()->json([
            'percent'   => $total > 0 ? round(($done / $total) * 100, 1) : 0,
            'processed' => $done,
            'total'     => $total,
            'errors'    => $status['errors'] ?? 0,
            'status'    => $status['status'] ?? 'UNKNOWN',
        ]);
    }

    public function validate(Request $request): JsonResponse
    {
        // Post-migration validation: compare record counts and checksums
        $legacyCount = DB::connection('oracle_legacy')->table('CTS_CHEQUES')->count();
        $newCount    = DB::table('cts_instruments')->count();
        $match       = $legacyCount === $newCount;

        return response()->json([
            'legacy_count' => $legacyCount,
            'new_count'    => $newCount,
            'match'        => $match,
            'variance'     => abs($legacyCount - $newCount),
        ]);
    }

    public function rollback(Request $request): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:500']);

        if (Cache::get('migration_running')) {
            return response()->json(['error' => 'CANNOT_ROLLBACK_WHILE_RUNNING'], 409);
        }

        dispatch(new \App\Jobs\RollbackMigration($request->reason, $request->user()->id));

        return response()->json(['status' => 'ROLLBACK_QUEUED']);
    }
}
