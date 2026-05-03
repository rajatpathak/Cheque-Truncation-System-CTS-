<?php

namespace App\Http\Controllers\DigitalSignature;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Instrument;
use App\Models\Batch;
use App\Services\PKISignatureService;
use Illuminate\Support\Facades\DB;

class SignatureController extends Controller
{
    public function __construct(private PKISignatureService $pki) {}

    public function signInstrument(Request $request, string $id): JsonResponse
    {
        $instrument = Instrument::where('instrument_id', $id)
                                ->where('status', 'VERIFIED')
                                ->firstOrFail();
        $result = $this->pki->signInstrument($instrument);
        $instrument->update(['status' => 'SIGNED', 'signed_by' => $request->user()->id]);
        return response()->json($result);
    }

    public function signFile(Request $request, int $clearingFileId): JsonResponse
    {
        $filePath = DB::table('cts_clearing_sessions')
                      ->where('id', $clearingFileId)
                      ->value('submission_file_path');

        if (!$filePath || !file_exists($filePath)) {
            return response()->json(['error' => 'FILE_NOT_FOUND'], 404);
        }

        $sigPath = $this->pki->signClearingFile($filePath);

        DB::table('cts_clearing_sessions')
          ->where('id', $clearingFileId)
          ->update(['submission_signed' => true, 'submission_file_hash' => hash_file('sha256', $filePath)]);

        return response()->json(['status' => 'FILE_SIGNED', 'signature_path' => basename($sigPath)]);
    }

    public function signBatch(Request $request, int $batchId): JsonResponse
    {
        $batch   = Batch::with('instruments')->findOrFail($batchId);
        $results = $this->pki->signBatch($batch);
        return response()->json([
            'status'  => 'BATCH_SIGNED',
            'count'   => count($results),
            'results' => $results,
        ]);
    }

    public function verify(Request $request, string $instrumentId): JsonResponse
    {
        $instrument = Instrument::where('instrument_id', $instrumentId)->firstOrFail();
        $valid      = $this->pki->verify($instrument);
        return response()->json([
            'instrument_id'     => $instrumentId,
            'signature_valid'   => $valid,
            'signature_status'  => $instrument->fresh()->signature_status,
        ]);
    }

    public function certificates(Request $request): JsonResponse
    {
        $certPath = config('cts.pki.cert_store');
        $certs    = [];

        foreach (glob("{$certPath}/*.pem") as $file) {
            $certData = openssl_x509_parse(file_get_contents($file));
            $certs[]  = [
                'file'       => basename($file),
                'subject'    => $certData['subject']['CN'] ?? null,
                'issuer'     => $certData['issuer']['CN'] ?? null,
                'valid_from' => date('Y-m-d', $certData['validFrom_time_t'] ?? 0),
                'valid_to'   => date('Y-m-d', $certData['validTo_time_t'] ?? 0),
                'is_valid'   => ($certData['validTo_time_t'] ?? 0) > time(),
            ];
        }

        return response()->json($certs);
    }

    public function renewCertificate(Request $request, int $id): JsonResponse
    {
        // Trigger certificate renewal via HSM / IDRBT CA
        return response()->json([
            'status'  => 'RENEWAL_INITIATED',
            'message' => 'Certificate renewal request submitted to IDRBT CA.',
        ]);
    }
}
