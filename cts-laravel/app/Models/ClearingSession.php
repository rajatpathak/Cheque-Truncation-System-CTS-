<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClearingSession extends Model
{
    protected $table = 'cts_clearing_sessions';

    protected $fillable = [
        'session_number',
        'session_date',
        'session_type',          // OUTWARD | INWARD | RETURN
        'clearing_type',
        'grid_code',
        'npci_grid_session_ref',
        'chi_session_ref',
        'dem_session_ref',
        'status',                // OPEN | PROCESSING | SUBMITTED | SETTLED | CLOSED
        'total_batches',
        'total_instruments',
        'total_outward_amount',
        'total_inward_amount',
        'total_return_amount',
        'submission_file_path',
        'submission_file_hash',
        'submission_signed',
        'submitted_at',
        'settlement_at',
        'opened_by',
        'closed_by',
        'remarks',
        'is_continuous_clearing',
        'eod_processed',
    ];

    protected $casts = [
        'session_date'            => 'date',
        'total_outward_amount'    => 'decimal:2',
        'total_inward_amount'     => 'decimal:2',
        'total_return_amount'     => 'decimal:2',
        'submission_signed'       => 'boolean',
        'is_continuous_clearing'  => 'boolean',
        'eod_processed'           => 'boolean',
        'submitted_at'            => 'datetime',
        'settlement_at'           => 'datetime',
    ];

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class, 'session_id');
    }

    public function instruments(): HasMany
    {
        return $this->hasMany(Instrument::class, 'session_id');
    }
}
