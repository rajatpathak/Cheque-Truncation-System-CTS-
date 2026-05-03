<?php

namespace App\Services;

use App\Models\Instrument;
use App\Models\FraudAlert;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class FraudDetectionService
{
    private OCRService  $ocr;
    private MICRService $micr;

    public function __construct(OCRService $ocr, MICRService $micr)
    {
        $this->ocr  = $ocr;
        $this->micr = $micr;
    }

    /**
     * Run the full fraud detection suite on an instrument.
     */
    public function scan(Instrument $instrument): array
    {
        $checks = [];
        $flags  = [];

        // 1. CTS 2010 Compliance
        $cts2010 = $this->checkCTS2010Compliance($instrument);
        $checks['cts2010'] = $cts2010;
        if (!$cts2010['compliant']) {
            $flags[] = 'CTS2010_NON_COMPLIANT';
        }

        // 2. UV Image Check
        $uvCheck = $this->checkUVImage($instrument);
        $checks['uv'] = $uvCheck;
        if (!$uvCheck['pass']) {
            $flags[] = 'UV_CHECK_FAILED';
        }

        // 3. QR Code / Barcode Verification
        $qrCheck = $this->checkQRCode($instrument);
        $checks['qr'] = $qrCheck;
        if (!$qrCheck['pass']) {
            $flags[] = 'QR_VERIFICATION_FAILED';
        }

        // 4. Duplicate Detection
        $dupCheck = $this->checkDuplicate($instrument);
        $checks['duplicate'] = $dupCheck;
        if ($dupCheck['is_duplicate']) {
            $flags[] = 'DUPLICATE_INSTRUMENT';
        }

        // 5. Photocopy / Tamper detection (via AI endpoint)
        $tamperCheck = $this->checkTamper($instrument);
        $checks['tamper'] = $tamperCheck;
        if ($tamperCheck['tampered']) {
            $flags[] = 'TAMPER_DETECTED';
        }
        if ($tamperCheck['photocopy']) {
            $flags[] = 'PHOTOCOPY_DETECTED';
        }
        if ($tamperCheck['torn_pasted']) {
            $flags[] = 'TORN_PASTED_DETECTED';
        }

        // 6. MICR anomaly check
        $micrCheck = $this->checkMICRAnomaly($instrument);
        $checks['micr'] = $micrCheck;
        if ($micrCheck['anomaly']) {
            $flags[] = 'MICR_ANOMALY';
        }

        // 7. Blacklist check
        $blacklistCheck = $this->checkBlacklist($instrument);
        $checks['blacklist'] = $blacklistCheck;
        if ($blacklistCheck['blacklisted']) {
            $flags[] = 'BLACKLISTED_ACCOUNT';
        }

        // 8. Non-CTS check
        if (!$cts2010['is_cts_standard']) {
            $flags[] = 'NON_CTS_INSTRUMENT';
        }

        $fraudStatus = empty($flags) ? 'CLEAR' : (
            count($flags) >= 3 || in_array('DUPLICATE_INSTRUMENT', $flags) || in_array('BLACKLISTED_ACCOUNT', $flags)
                ? 'BLOCKED'
                : 'SUSPICIOUS'
        );

        $this->saveResult($instrument, $flags, $fraudStatus, $checks, $cts2010, $uvCheck, $qrCheck);

        // Block submission if fraud detected
        if ($fraudStatus === 'BLOCKED') {
            $instrument->update(['status' => 'BLOCKED', 'fraud_status' => 'BLOCKED']);
        } else {
            $instrument->update([
                'fraud_status'     => $fraudStatus,
                'fraud_flags'      => $flags,
                'cts2010_compliant'=> $cts2010['compliant'],
                'uv_check_status'  => $uvCheck['pass'] ? 'PASS' : 'FAIL',
                'qr_code_data'     => $qrCheck['data'] ?? null,
            ]);
        }

        return [
            'instrument_id' => $instrument->instrument_id,
            'status'        => $fraudStatus,
            'flags'         => $flags,
            'checks'        => $checks,
        ];
    }

    private function checkCTS2010Compliance(Instrument $instrument): array
    {
        if (!$instrument->image_path_uv) {
            return ['compliant' => false, 'is_cts_standard' => false, 'violations' => ['NO_UV_IMAGE']];
        }

        // Call AI service for CTS 2010 specific checks
        $response = Http::timeout(20)->post(config('services.ai_engine.url') . '/cts2010', [
            'image_grey' => $instrument->image_path_grey,
            'image_uv'   => $instrument->image_path_uv,
        ]);

        if ($response->failed()) {
            return ['compliant' => false, 'is_cts_standard' => true, 'violations' => ['AI_CHECK_FAILED']];
        }

        return $response->json();
    }

    private function checkUVImage(Instrument $instrument): array
    {
        if (!$instrument->image_path_uv) {
            return ['pass' => false, 'reason' => 'NO_UV_IMAGE'];
        }
        // UV image analysis — watermark and security thread detection
        $response = Http::timeout(20)->post(config('services.ai_engine.url') . '/uv-check', [
            'image_uv' => $instrument->image_path_uv,
        ]);
        return $response->successful() ? $response->json() : ['pass' => false, 'reason' => 'UV_ENGINE_ERROR'];
    }

    private function checkQRCode(Instrument $instrument): array
    {
        $imagick = new \Imagick($instrument->image_path_grey);
        // QR detection using ZBar or AI endpoint
        $response = Http::timeout(10)->post(config('services.ai_engine.url') . '/qr-detect', [
            'image' => $instrument->image_path_grey,
        ]);
        return $response->successful() ? $response->json() : ['pass' => true, 'data' => null];
    }

    private function checkDuplicate(Instrument $instrument): array
    {
        $existing = Instrument::where('cheque_number', $instrument->cheque_number)
            ->where('micr_code', $instrument->micr_code)
            ->where('amount_figures', $instrument->amount_figures)
            ->where('instrument_date', $instrument->instrument_date)
            ->where('id', '!=', $instrument->id)
            ->where('status', '!=', 'RETURNED')
            ->first();

        return [
            'is_duplicate' => (bool) $existing,
            'duplicate_id' => $existing?->instrument_id,
        ];
    }

    private function checkTamper(Instrument $instrument): array
    {
        $response = Http::timeout(20)->post(config('services.ai_engine.url') . '/tamper-detect', [
            'image_grey' => $instrument->image_path_grey,
            'image_uv'   => $instrument->image_path_uv,
        ]);
        return $response->successful() ? $response->json() : [
            'tampered'   => false,
            'photocopy'  => false,
            'torn_pasted'=> false,
        ];
    }

    private function checkMICRAnomaly(Instrument $instrument): array
    {
        $validation = $this->micr->validate($this->micr->parse($instrument->micr_code ?? ''));
        return ['anomaly' => !$validation['valid'], 'errors' => $validation['errors']];
    }

    private function checkBlacklist(Instrument $instrument): array
    {
        $blacklisted = DB::table('cts_blacklisted_accounts')
            ->where('account_number', $instrument->account_number)
            ->where('active', true)
            ->exists();

        return ['blacklisted' => $blacklisted];
    }

    private function saveResult(
        Instrument $instrument,
        array $flags,
        string $fraudStatus,
        array $checks,
        array $cts2010,
        array $uvCheck,
        array $qrCheck
    ): void {
        if (!empty($flags)) {
            FraudAlert::create([
                'instrument_id'       => $instrument->id,
                'alert_type'          => implode(',', $flags),
                'severity'            => $fraudStatus === 'BLOCKED' ? 'HIGH' : 'MEDIUM',
                'checks_failed'       => $flags,
                'cts2010_violations'  => $cts2010['violations'] ?? [],
                'uv_status'           => $uvCheck['pass'] ? 'PASS' : 'FAIL',
                'qr_status'           => $qrCheck['pass'] ?? true ? 'PASS' : 'FAIL',
                'duplicate_of'        => $checks['duplicate']['duplicate_id'] ?? null,
                'tamper_detected'     => in_array('TAMPER_DETECTED', $flags),
                'photocopy_detected'  => in_array('PHOTOCOPY_DETECTED', $flags),
                'torn_pasted_detected'=> in_array('TORN_PASTED_DETECTED', $flags),
                'micr_anomaly'        => in_array('MICR_ANOMALY', $flags),
                'description'         => "Fraud flags: " . implode(', ', $flags),
                'status'              => 'OPEN',
                'auto_blocked'        => $fraudStatus === 'BLOCKED',
            ]);
        }
    }
}
