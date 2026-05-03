<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CTS Web Routes — minimal; all business logic is via API routes
|--------------------------------------------------------------------------
*/
Route::get('/', fn() => response()->json([
    'system'   => 'Indian Overseas Bank - CTS National Grid',
    'version'  => '1.0.0',
    'tender'   => 'GEM/2026/B/7367951',
    'status'   => 'operational',
    'dc'       => config('cts.grid.dc.name') . ' (' . config('cts.grid.dc.location') . ')',
    'dr'       => config('cts.grid.dr.name') . ' (' . config('cts.grid.dr.location') . ')',
]));

Route::get('/healthz', fn() => response()->json([
    'status'     => 'ok',
    'node'       => cache()->get('active_node', 'dc'),
    'timestamp'  => now()->toIso8601String(),
]));
