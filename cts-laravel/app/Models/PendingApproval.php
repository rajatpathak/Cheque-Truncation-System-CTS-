<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingApproval extends Model
{
    protected $table = 'cts_pending_approvals';

    protected $fillable = [
        'maker_id', 'maker_name', 'branch_code',
        'module', 'action', 'payload',
        'route', 'method', 'status',
        'checker_level',
        'checker1_id', 'checker1_at', 'checker1_remarks',
        'checker2_id', 'checker2_at', 'checker2_remarks',
        'rejected_by', 'rejected_at', 'rejection_reason',
        'expires_at',
    ];

    protected $casts = [
        'checker1_at'  => 'datetime',
        'checker2_at'  => 'datetime',
        'rejected_at'  => 'datetime',
        'expires_at'   => 'datetime',
    ];

    public function maker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'maker_id');
    }

    public function isExpired(): bool
    {
        return now()->isAfter($this->expires_at);
    }

    public function decryptPayload(): array
    {
        return json_decode(decrypt($this->payload), true);
    }
}
