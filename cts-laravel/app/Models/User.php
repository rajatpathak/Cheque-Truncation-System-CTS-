<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'branch_code',
        'mobile',
        'is_active',
        'mfa_enabled',
        'mfa_methods',
        'daily_cheque_limit',
        'password_changed_at',
        'last_login_at',
        'last_login_ip',
        'disabled_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at'   => 'datetime',
        'password'            => 'hashed',
        'is_active'           => 'boolean',
        'mfa_enabled'         => 'boolean',
        'mfa_methods'         => 'array',
        'daily_cheque_limit'  => 'integer',
        'password_changed_at' => 'datetime',
        'last_login_at'       => 'datetime',
        'disabled_at'         => 'datetime',
    ];

    public function pendingApprovals(): HasMany
    {
        return $this->hasMany(PendingApproval::class, 'maker_id');
    }

    public function auditTrails(): HasMany
    {
        return $this->hasMany(AuditTrail::class, 'user_id');
    }

    public function isPasswordExpired(): bool
    {
        if (!$this->password_changed_at) return true;
        return $this->password_changed_at->diffInDays(now()) > config('cts.security.password_expiry_days');
    }
}
