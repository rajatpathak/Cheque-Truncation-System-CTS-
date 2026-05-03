<?php

namespace App\Http\Controllers\ReturnProcessing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\ReturnInstrument;
use App\Services\NotificationService;
use Barryvdh\DomPDF\Facade\Pdf;

class ReturnMemoController extends Controller
{
    public function __construct(private NotificationService $notify) {}

    public function generate(Request $request, int $id): mixed
    {
        $return = ReturnInstrument::with('originalInstrument')->findOrFail($id);

        $pdf = Pdf::loadView('returns.memo', [
            'return'    => $return,
            'bank_name' => config('cts.bank.name'),
            'date'      => now()->format('d-M-Y'),
        ]);

        $memoPath = storage_path("app/memos/return_memo_{$return->id}.pdf");
        $pdf->save($memoPath);
        $return->update(['memo_generated' => true, 'memo_path' => $memoPath]);

        if ($request->format === 'pdf') {
            return $pdf->download("return_memo_{$return->id}.pdf");
        }

        return response()->json(['status' => 'GENERATED', 'memo_id' => $return->id]);
    }

    public function bulkGenerate(Request $request): JsonResponse
    {
        $request->validate(['session_id' => 'required|exists:cts_clearing_sessions,id']);

        $returns = ReturnInstrument::where('session_id', $request->session_id)
                                   ->where('memo_generated', false)
                                   ->get();

        foreach ($returns as $return) {
            dispatch(new \App\Jobs\GenerateReturnMemo($return->id));
        }

        return response()->json(['status' => 'BULK_QUEUED', 'count' => $returns->count()]);
    }

    public function email(Request $request, int $id): JsonResponse
    {
        $request->validate(['account_number' => 'nullable|string']);

        $return = ReturnInstrument::with('originalInstrument')->findOrFail($id);

        if (!$return->memo_path || !file_exists($return->memo_path)) {
            $this->generate($request, $id);
            $return->refresh();
        }

        $sent = $this->notify->emailReturnMemo(
            $request->account_number ?? $return->originalInstrument?->account_number,
            $return->memo_path,
            [
                'cheque_number'  => $return->originalInstrument?->cheque_number,
                'instrument_date'=> $return->originalInstrument?->instrument_date,
                'amount'         => $return->amount,
                'return_reason'  => $return->return_reason_description,
            ]
        );

        $return->update(['memo_emailed' => $sent]);

        return response()->json(['status' => $sent ? 'EMAIL_SENT' : 'EMAIL_FAILED']);
    }
}
