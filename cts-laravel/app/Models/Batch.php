<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Batch extends Model
{
    protected $table = 'cts_batches';

    protected $fillable = [
        'batch_number',
        'batch_type',           // CTS | NONCTS | SPECIAL | RETURN | GOVT | P2F
        'branch_code',
        'grid_code',
        'session_id',
        'status',               // OPEN | CLOSED | SUBMITTED | ACCEPTED | REJECTED
        'total_instruments',
        'total_amount',
        'iqa_pass_count',
        'iqa_fail_count',
        'data_entry_complete',
        'verification_complete',
        'signed',
        'submitted_to_chi',
        'chi_reference',
        'chi_submission_time',
        'chi_response',
        'created_by',
        'closed_by',
        'submitted_by',
        'scanner_device_id',
        'scan_mode',            // DISTRIBUTED | CENTRALIZED
        'remarks',
    ];

    protected $casts = [
        'total_amount'           => 'decimal:2',
        'data_entry_complete'    => 'boolean',
        'verification_complete'  => 'boolean',
        'signed'                 => 'boolean',
        'submitted_to_chi'       => 'boolean',
        'chi_response'           => 'array',
        'chi_submission_time'    => 'datetime',
    ];

    public function instruments(): HasMany
    {
        return $this->hasMany(Instrument::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ClearingSession::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
