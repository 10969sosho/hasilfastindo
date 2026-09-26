<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'branch_id', 'phone', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_CENTRAL = 'central';

    public const ROLE_BRANCH_STAFF = 'branch_staff';

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isCentral(): bool
    {
        return $this->role === self::ROLE_CENTRAL || $this->isSuperAdmin();
    }

    public function isBranchStaff(): bool
    {
        return $this->role === self::ROLE_BRANCH_STAFF;
    }

    /**
     * Cabang yang boleh diakses user ini (null = semua cabang).
     */
    public function scopeAccessibleBranch($query)
    {
        if ($this->isSuperAdmin()) {
            return $query;
        }

        if ($this->branch_id === null) {
            return $query;
        }

        return $query->where('branch_id', $this->branch_id);
    }
}
