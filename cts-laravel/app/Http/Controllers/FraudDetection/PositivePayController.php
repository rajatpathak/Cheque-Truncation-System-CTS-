<?php

namespace App\Http\Controllers\FraudDetection;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Instrument;
use App\Services\PositivePayService;

class PositivePayController extends Controller
{
    public function __construct(private PositivePayService $positivePay) {}

    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'account_number'  => 'required|string',
            'cheque_number'   => 'required|string',
            'amount'          => 'required|numeric',
            'instrument_date' => 'required|date',
            'payee_name'      => 'required|string',
            'bank_sort_code'  => 'nullable|string',
        ]);

        $result = $this->positivePay->check($request->all());
        return response()->json($result);
    }

    public function status(Request $request, string $ref): JsonResponse
    {
        // Retrieve positive pay check status by reference
        $record = \DB::table('cts_positive_pay_log')
            ->where('reference', $ref)
            ->first();

        return response()->json($record ?: ['error' => 'NOT_FOUND'], $record ? 200 : 404);
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'account_number'  => 'required|string',
            'cheque_number'   => 'required|string',
            'amount'          => 'required|numeric',
            'payee_name'      => 'required|string',
            'issue_date'      => 'required|date',
        ]);

        $result = $this->positivePay->register($request->all());
        return response()->json($result, $result['success'] ? 201 : 422);
    }
}
