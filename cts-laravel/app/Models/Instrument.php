<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Instrument extends Model
{
    protected $table = 'cts_instruments';

    protected $fillable = [
        'instrument_id', 'batch_id', 'session_id',
        'clearing_type',       // CTS | NONCTS | SPECIAL | RETURN | GOVT
        'cheque_number',
        'micr_code',           // Full 30-digit MICR band
        'bank_sort_code',      // 9-digit presenting bank sort code
        'account_number',
        'account_name',
        'payee_name',
        'amount_figures',
        'amount_words',
        'instrument_date',
        'presentment_date',
        'drawer_bank_code',
        'drawer_branch_code',
        'branch_code',
        'grid_code',
        'region_code',
        'zone_code',
        'san_number',
        'image_path_grey',
        'image_path_bw',
        'image_path_uv',
        'image_hash_grey',
        'image_hash_bw',
        'image_hash_uv',
        'iqa_status',          // PASS | FAIL | PENDING
        'iqa_failure_reasons', // JSON array
        'ocr_data',            // JSON: extracted fields
        'signature_status',    // UNSIGNED | SIGNED | VERIFIED
        'digital_signature',
        'fraud_status',        // CLEAR | SUSPICIOUS | BLOCKED
        'fraud_flags',         // JSON array of triggered checks
        'cts2010_compliant',
        'uv_check_status',
        'qr_code_data',
        'positive_pay_status', // VERIFIED | UNVERIFIED | FAILED
        'account_validated',
        'status',              // SCANNED|DATA_ENTRY|VERIFIED|SIGNED|SUBMITTED|RETURNED|ARCHIVED
        'hold_reason',
        'remarks',
        'item_sequence_number',
        'endorsement_text',
        'is_government_cheque',
        'is_high_value',
        'high_value_alert_sent',
        'processed_by',
        'verified_by',
        'signed_by',
        'submitted_at',
        'archived_at',
        'is_archived',
    ];

    protected $casts = [
        'iqa_failure_reasons' => 'array',
        'ocr_data'            => 'array',
        'fraud_flags'         => 'array',
        'amount_figures'      => 'decimal:2',
        'is_government_cheque'=> 'boolean',
        'is_high_value'       => 'boolean',
        'high_value_alert_sent'=> 'boolean',
        'account_validated'   => 'boolean',
        'cts2010_compliant'   => 'boolean',
        'is_archived'         => 'boolean',
        'instrument_date'     => 'date',
        'presentment_date'    => 'date',
        'submitted_at'        => 'datetime',
        'archived_at'         => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ClearingSession::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ReturnInstrument::class);
    }

    public function auditTrails(): HasMany
    {
        return $this->hasMany(AuditTrail::class, 'reference_id')
                    ->where('reference_type', 'instrument');
    }

    public function isHighValue(): bool
    {
        return $this->amount_figures >= config('cts.processing.high_value_threshold');
    }

    public function requiresDualVerification(): bool
    {
        return $this->amount_figures >= config('cts.processing.dual_verify_threshold');
    }
}
