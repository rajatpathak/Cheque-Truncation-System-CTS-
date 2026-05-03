<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DRDrill extends Model
{
    protected $table = 'cts_dr_drills';

    protected $fillable = [
        'drill_type',           // SCHEDULED | UNSCHEDULED
        'initiated_by',
        'scheduled_at',
        'started_at',
        'completed_at',
        'dc_to_dr_switchover_time_seconds',
        'dr_to_dc_switchover_time_seconds',
        'rto_achieved_minutes',
        'rpo_achieved_minutes',
        'status',               // SCHEDULED | IN_PROGRESS | COMPLETED | FAILED
        'outcome',              // PASS | FAIL | PARTIAL
        'participants',         // JSON array
        'observations',
        'report_path',
        'sla_rto_target'  ,
        'sla_rpo_target',
    ];

    protected $casts = [
        'scheduled_at'    => 'datetime',
        'started_at'      => 'datetime',
        'completed_at'    => 'datetime',
        'participants'    => 'array',
    ];
}
