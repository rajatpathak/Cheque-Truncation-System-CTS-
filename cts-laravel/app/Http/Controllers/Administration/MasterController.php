<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MasterController extends Controller
{
    public function branchIndex(Request $request): JsonResponse
    {
        return response()->json(DB::table('cts_branch_master')->where('active', true)->paginate(50));
    }

    public function branchStore(Request $request): JsonResponse
    {
        $request->validate([
            'branch_code' => 'required|unique:cts_branch_master',
            'branch_name' => 'required|string',
            'grid_code'   => 'required|string',
            'sort_code'   => 'required|string|size:9',
            'ifsc_code'   => 'required|string|size:11',
        ]);

        DB::table('cts_branch_master')->insert($request->only([
            'branch_code', 'branch_name', 'region_code', 'zone_code',
            'grid_code', 'sort_code', 'ifsc_code', 'san_number',
            'address', 'city', 'state', 'pincode', 'hub_branch', 'is_hub',
        ]) + ['active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['status' => 'BRANCH_CREATED'], 201);
    }

    public function branchUpdate(Request $request, string $code): JsonResponse
    {
        DB::table('cts_branch_master')
          ->where('branch_code', $code)
          ->update($request->only(['branch_name', 'grid_code', 'region_code', 'zone_code', 'hub_branch']) + ['updated_at' => now()]);
        return response()->json(['status' => 'UPDATED']);
    }

    public function bankList(Request $request): JsonResponse
    {
        return response()->json(
            DB::table('cts_bank_branch_master')
              ->distinct('bank_name', 'bank_code')
              ->where('active', true)
              ->get(['bank_name', 'bank_code'])
        );
    }

    public function clearingHouseList(Request $request): JsonResponse
    {
        return response()->json(
            DB::table('cts_clearing_sessions')
              ->distinct('grid_code')
              ->pluck('grid_code')
        );
    }

    public function returnReasonCodes(Request $request): JsonResponse
    {
        return response()->json(DB::table('cts_return_reason_codes')->where('active', true)->get());
    }
}
