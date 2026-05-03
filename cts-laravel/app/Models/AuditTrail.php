<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditTrail extends Model
{
    protected $table = 'cts_audit_trail';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'user_name', 'branch_code',
        'ip_address', 'action', 'module',
        'reference_type', 'reference_id',
        'request_body', 'response_code',
        'before_value', 'after_value',
        'timestamp', 'session_id',
        'risk_level',
    ];

    protected $casts = [
        'request_body' => 'array',
        'before_value' => 'array',
        'after_value'  => 'array',
        'timestamp'    => 'datetime',
    ];
}
