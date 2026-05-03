<?php

namespace App\Services;

use App\Models\Instrument;
use App\Models\Batch;
use Illuminate\Support\Facades\Log;
use App\Exceptions\SignatureException;

class PKISignatureService
{
    private string $hsmHost;
    private int    $hsmPort;
    private string $certPath;
    private string $keyPath;
    private string $algorithm;

    public function __construct()
    {
        $this->hsmHost   = config('cts.pki.hsm_host');
        $this->hsmPort   = config('cts.pki.hsm_port');
        $this->certPath  = config('cts.pki.cert_store');
        $this->algorithm = config('cts.pki.signing_algorithm');
    }

    /**
     * Sign a single instrument (RBI mandate — each instrument must be signed).
     */
    public function signInstrument(Instrument $instrument): array
    {
        $payload   = $this->buildInstrumentPayload($instrument);
        $signature = $this->sign($payload);
        $hash      = $this->hashImageData($instrument);

        $instrument->update([
            'digital_signature' => $signature,
            'image_hash_grey'   => $hash['grey'],
            'image_hash_bw'     => $hash['bw'],
            'image_hash_uv'     => $hash['uv'],
            'signature_status'  => 'SIGNED',
            'signed_by'         => auth()->id(),
        ]);

        Log::info('Instrument signed', ['instrument_id' => $instrument->instrument_id]);

        return ['status' => 'SIGNED', 'signature' => substr($signature, 0, 16) . '...'];
    }

    /**
     * Sign an entire clearing file (file-level signature per RBI/NPCI spec).
     */
    public function signClearingFile(string $filePath): string
    {
        $fileContent = file_get_contents($filePath);
        $signature   = $this->sign(base64_encode($fileContent));

        $sigFilePath = $filePath . '.sig';
        file_put_contents($sigFilePath, $signature);

        Log::info('Clearing file signed', ['file' => $filePath]);

        return $sigFilePath;
    }

    /**
     * Batch sign all instruments in a batch (central-level signing for grid submission).
     */
    public function signBatch(Batch $batch): array
    {
        $results = [];
        foreach ($batch->instruments as $instrument) {
            $results[$instrument->instrument_id] = $this->signInstrument($instrument);
        }

        $batch->update(['signed' => true]);
        return $results;
    }

    /**
     * Verify an existing instrument signature.
     */
    public function verify(Instrument $instrument): bool
    {
        if (!$instrument->digital_signature) {
            return false;
        }

        $payload   = $this->buildInstrumentPayload($instrument);
        $certData  = $this->loadCertificate();

        $result = openssl_verify(
            $payload,
            base64_decode($instrument->digital_signature),
            $certData,
            OPENSSL_ALGO_SHA256
        );

        $instrument->update(['signature_status' => $result === 1 ? 'VERIFIED' : 'INVALID']);

        return $result === 1;
    }

    /**
     * Hash instrument images for tamper detection (SHA-256).
     */
    public function hashImageData(Instrument $instrument): array
    {
        return [
            'grey' => $instrument->image_path_grey ? hash_file('sha256', $instrument->image_path_grey) : null,
            'bw'   => $instrument->image_path_bw   ? hash_file('sha256', $instrument->image_path_bw)   : null,
            'uv'   => $instrument->image_path_uv   ? hash_file('sha256', $instrument->image_path_uv)   : null,
        ];
    }

    private function sign(string $data): string
    {
        // In production: route through HSM via PKCS#11 interface
        // For now: use file-based private key
        $privateKey = openssl_pkey_get_private(
            file_get_contents("{$this->certPath}/private.pem"),
            env('PKI_KEY_PASSPHRASE')
        );

        if (!$privateKey) {
            throw new SignatureException('Failed to load private key from PKI store.');
        }

        $signature = '';
        openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    private function loadCertificate(): string
    {
        return file_get_contents("{$this->certPath}/public.pem");
    }

    private function buildInstrumentPayload(Instrument $instrument): string
    {
        return implode('|', [
            $instrument->instrument_id,
            $instrument->cheque_number,
            $instrument->micr_code,
            $instrument->amount_figures,
            $instrument->instrument_date,
            $instrument->account_number,
            $instrument->image_hash_grey ?? '',
        ]);
    }
}
