<?php

namespace App\Http\Controllers\OutwardClearing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Instrument;
use App\Services\IQAService;
use App\Services\MICRService;
use App\Services\PKISignatureService;
use App\Services\NotificationService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ScanController extends Controller
{
    public function __construct(
        private IQAService          $iqa,
        private MICRService         $micr,
        private PKISignatureService $pki,
        private NotificationService $notify
    ) {}

    /**
     * Capture a scanned cheque image and initiate processing pipeline.
     * Accepts: GREY, BW, UV image files + MICR data from scanner.
     */
    public function capture(Request $request): JsonResponse
    {
        $request->validate([
            'batch_id'       => 'required|exists:cts_batches,id',
            'micr_data'      => 'required|string|min:28|max:35',
            'image_grey'     => 'required|file|mimes:tiff,tif,jpg,png',
            'image_bw'       => 'nullable|file|mimes:tiff,tif,jpg,png',
            'image_uv'       => 'nullable|file|mimes:tiff,tif,jpg,png',
            'scanner_device_id' => 'nullable|string',
        ]);

        $instrumentId = Str::uuid();
        $basePath     = "instruments/{$instrumentId}";

        // Store images
        $greyPath = $request->file('image_grey')->storeAs($basePath, 'grey.tif', 'local');
        $bwPath   = $request->file('image_bw')?->storeAs($basePath, 'bw.tif', 'local');
        $uvPath   = $request->file('image_uv')?->storeAs($basePath, 'uv.tif', 'local');

        // Run IQA on grey image
        $iqaResult = $this->iqa->check(storage_path("app/{$greyPath}"));

        // Parse MICR
        $micrParsed   = $this->micr->parse($request->micr_data);
        $micrValidated = $this->micr->validate($micrParsed);

        // Generate Item Sequence Number (ISN)
        $isn = $this->generateISN($request->batch_id);

        $instrument = Instrument::create([
            'instrument_id'       => $instrumentId,
            'batch_id'            => $request->batch_id,
            'cheque_number'       => $micrParsed['cheque_number'],
            'micr_code'           => $micrParsed['full_micr'],
            'bank_sort_code'      => $micrParsed['bank_sort_code'],
            'account_number'      => $micrParsed['account_number'],
            'image_path_grey'     => storage_path("app/{$greyPath}"),
            'image_path_bw'       => $bwPath ? storage_path("app/{$bwPath}") : null,
            'image_path_uv'       => $uvPath ? storage_path("app/{$uvPath}") : null,
            'iqa_status'          => $iqaResult['status'],
            'iqa_failure_reasons' => $iqaResult['failures'],
            'item_sequence_number'=> $isn,
            'branch_code'         => $request->user()->branch_code,
            'status'              => $iqaResult['status'] === 'PASS' ? 'SCANNED' : 'IQA_FAILED',
            'processed_by'        => $request->user()->id,
            'clearing_type'       => 'CTS',
            'signature_status'    => 'UNSIGNED',
            'fraud_status'        => 'PENDING',
        ]);

        return response()->json([
            'status'        => 'CAPTURED',
            'instrument_id' => $instrumentId,
            'iqa_status'    => $iqaResult['status'],
            'iqa_failures'  => $iqaResult['failures'],
            'micr_valid'    => $micrValidated['valid'],
            'micr_errors'   => $micrValidated['errors'],
            'isn'           => $isn,
        ], 201);
    }

    public function rescan(Request $request, string $instrumentId): JsonResponse
    {
        $instrument = Instrument::where('instrument_id', $instrumentId)
                                ->where('iqa_status', 'FAIL')
                                ->firstOrFail();

        $request->validate([
            'image_grey' => 'required|file|mimes:tiff,tif,jpg,png',
            'image_bw'   => 'nullable|file|mimes:tiff,tif,jpg,png',
            'image_uv'   => 'nullable|file|mimes:tiff,tif,jpg,png',
        ]);

        $basePath = "instruments/{$instrumentId}/rescan_" . now()->format('YmdHis');
        $greyPath = $request->file('image_grey')->storeAs($basePath, 'grey.tif', 'local');

        $iqaResult = $this->iqa->check(storage_path("app/{$greyPath}"));

        $instrument->update([
            'image_path_grey'     => storage_path("app/{$greyPath}"),
            'iqa_status'          => $iqaResult['status'],
            'iqa_failure_reasons' => $iqaResult['failures'],
            'status'              => $iqaResult['status'] === 'PASS' ? 'SCANNED' : 'IQA_FAILED',
        ]);

        return response()->json([
            'iqa_status'  => $iqaResult['status'],
            'iqa_failures'=> $iqaResult['failures'],
        ]);
    }

    public function iqaStatus(Request $request, string $instrumentId): JsonResponse
    {
        $instrument = Instrument::where('instrument_id', $instrumentId)->firstOrFail();
        return response()->json([
            'iqa_status'    => $instrument->iqa_status,
            'iqa_failures'  => $instrument->iqa_failure_reasons,
        ]);
    }

    public function bulkUpload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xls,xlsx',
            'batch_id' => 'required|exists:cts_batches,id',
        ]);

        // Dispatch bulk processing job
        dispatch(new \App\Jobs\ProcessBulkUpload(
            $request->file('file')->store('bulk-uploads'),
            $request->batch_id,
            $request->user()->id
        ));

        return response()->json(['status' => 'QUEUED', 'message' => 'Bulk upload queued for processing.']);
    }

    public function hold(Request $request, string $instrumentId): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:500']);
        Instrument::where('instrument_id', $instrumentId)
                  ->update(['status' => 'HOLD', 'hold_reason' => $request->reason]);
        return response()->json(['status' => 'ON_HOLD']);
    }

    public function release(Request $request, string $instrumentId): JsonResponse
    {
        Instrument::where('instrument_id', $instrumentId)
                  ->where('status', 'HOLD')
                  ->update(['status' => 'SCANNED', 'hold_reason' => null]);
        return response()->json(['status' => 'RELEASED']);
    }

    public function addRemark(Request $request, string $instrumentId): JsonResponse
    {
        $request->validate(['remark' => 'required|string|max:500']);
        Instrument::where('instrument_id', $instrumentId)->update(['remarks' => $request->remark]);
        return response()->json(['status' => 'REMARK_ADDED']);
    }

    public function instruments(Request $request): JsonResponse
    {
        $query = Instrument::query()
            ->when($request->batch_id, fn($q) => $q->where('batch_id', $request->batch_id))
            ->when($request->status,   fn($q) => $q->where('status', $request->status))
            ->when($request->iqa,      fn($q) => $q->where('iqa_status', $request->iqa))
            ->when($request->date,     fn($q) => $q->whereDate('created_at', $request->date))
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($query);
    }

    public function instrument(Request $request, string $id): JsonResponse
    {
        return response()->json(
            Instrument::where('instrument_id', $id)->firstOrFail()
        );
    }

    public function listDevices(Request $request): JsonResponse
    {
        $devices = \DB::table('cts_scanner_devices')
            ->where('branch_code', $request->user()->branch_code)
            ->where('active', true)
            ->get();
        return response()->json($devices);
    }

    private function generateISN(int $batchId): string
    {
        $seq = \DB::table('cts_batches')->where('id', $batchId)->value('total_instruments') + 1;
        \DB::table('cts_batches')->where('id', $batchId)->increment('total_instruments');
        return str_pad($batchId, 8, '0', STR_PAD_LEFT) . str_pad($seq, 6, '0', STR_PAD_LEFT);
    }
}
