<?php

namespace App\Services;

use App\Models\Instrument;
use Illuminate\Support\Facades\Cache;

class MICRService
{
    /**
     * Parse a raw 30-character MICR band string.
     * Format: CCCCCCBBBBBBAAAAAAAAPPPTTTT
     *   C = Cheque No (6)
     *   B = Bank Sort Code (9)
     *   A = Account No (6)
     *   P = Presenting Bank Sort Code (9) — added by CTS
     *   T = Transaction Code (2) + City Code (3)
     */
    public function parse(string $micrBand): array
    {
        $micrBand = preg_replace('/[^0-9]/', '', $micrBand);

        return [
            'cheque_number'        => substr($micrBand, 0, 6),
            'bank_sort_code'       => substr($micrBand, 6, 9),
            'account_number'       => substr($micrBand, 15, 6),
            'transaction_code'     => substr($micrBand, 21, 2),
            'city_code'            => substr($micrBand, 23, 3),
            'full_micr'            => $micrBand,
            'length'               => strlen($micrBand),
        ];
    }

    /**
     * Validate parsed MICR data against the branch master.
     */
    public function validate(array $parsed): array
    {
        $errors = [];

        if (strlen($parsed['cheque_number']) !== 6)
            $errors[] = 'INVALID_CHEQUE_NUMBER_LENGTH';

        if (strlen($parsed['bank_sort_code']) !== 9)
            $errors[] = 'INVALID_SORT_CODE_LENGTH';

        if (!$this->isSortCodeKnown($parsed['bank_sort_code']))
            $errors[] = 'UNKNOWN_SORT_CODE';

        if (!is_numeric($parsed['account_number']) || strlen($parsed['account_number']) !== 6)
            $errors[] = 'INVALID_ACCOUNT_NUMBER';

        if (!in_array($parsed['transaction_code'], ['10', '11', '12', '13', '50', '51']))
            $errors[] = 'INVALID_TRANSACTION_CODE';

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
            'parsed' => $parsed,
        ];
    }

    public function isSortCodeKnown(string $sortCode): bool
    {
        // Cache bank master for 1 hour
        $master = Cache::remember('micr_sort_code_master', 3600, function () {
            return \DB::table('cts_bank_branch_master')
                      ->pluck('sort_code')
                      ->toArray();
        });

        return in_array($sortCode, $master);
    }

    public function resolveBank(string $sortCode): ?array
    {
        return Cache::remember("sort_code_{$sortCode}", 3600, function () use ($sortCode) {
            return \DB::table('cts_bank_branch_master')
                      ->where('sort_code', $sortCode)
                      ->first(['bank_name', 'branch_name', 'city_code', 'ifsc_code']);
        });
    }

    public function updateInstrument(Instrument $instrument, array $parsed): void
    {
        $instrument->update([
            'micr_code'       => $parsed['full_micr'],
            'cheque_number'   => $parsed['cheque_number'],
            'bank_sort_code'  => $parsed['bank_sort_code'],
            'account_number'  => $parsed['account_number'],
        ]);
    }
}
