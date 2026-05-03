<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnInstrument extends Model
{
    protected $table = 'cts_return_instruments';

    protected $fillable = [
        'instrument_id',
        'original_instrument_id',
        'return_type',              // INWARD_RETURN | OUTWARD_RETURN | CHI_REJECTED
        'return_reason_code',
        'return_reason_description',
        'return_date',
        'clearing_date',
        'return_clearing_date',
        'batch_id',
        'session_id',
        'branch_code',
        'amount',
        'memo_generated',
        'memo_path',
        'memo_emailed',
        'signed',
        'submitted_to_chi',
        'chi_reference',
        'representment_count',
        'last_representment_at',
        'status',                   // PENDING | SIGNED | SUBMITTED | SETTLED
        'processed_by',
        'signed_by',
        'micr_corrected',
        'iqa_override',
        'remarks',
    ];

    protected $casts = [
        'amount'             => 'decimal:2',
        'return_date'        => 'date',
        'clearing_date'      => 'date',
        'return_clearing_date' => 'date',
        'memo_generated'     => 'boolean',
        'memo_emailed'       => 'boolean',
        'signed'             => 'boolean',
        'submitted_to_chi'   => 'boolean',
        'micr_corrected'     => 'boolean',
        'iqa_override'       => 'boolean',
        'last_representment_at' => 'datetime',
    ];

    public function originalInstrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class, 'original_instrument_id');
    }
}
