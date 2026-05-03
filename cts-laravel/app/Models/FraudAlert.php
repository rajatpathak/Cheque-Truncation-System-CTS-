<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudAlert extends Model
{
    protected $table = 'cts_fraud_alerts';

    protected $fillable = [
        'instrument_id', 'alert_type', 'severity',
        'checks_failed', 'cts2010_violations',
        'uv_status', 'qr_status', 'duplicate_of',
        'tamper_detected', 'photocopy_detected',
        'torn_pasted_detected', 'micr_anomaly',
        'description', 'status',
        'resolved_by', 'resolved_at', 'resolution_notes',
        'auto_blocked',
    ];

    protected $casts = [
        'checks_failed'       => 'array',
        'cts2010_violations'  => 'array',
        'tamper_detected'     => 'boolean',
        'photocopy_detected'  => 'boolean',
        'torn_pasted_detected'=> 'boolean',
        'micr_anomaly'        => 'boolean',
        'auto_blocked'        => 'boolean',
        'resolved_at'         => 'datetime',
    ];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
