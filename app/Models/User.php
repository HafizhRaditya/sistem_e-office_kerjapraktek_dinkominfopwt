<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Domain user. Login identity is `nip_nik` (NIP or NIK), not email.
 * `role` is a CHECK-constrained column ('admin' | 'pegawai'), not a pivot.
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'opd_id',
        'nip_nik',
        'name',
        'email',
        'email_verified_at',
        'password',
        'role',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    /** Applications this user may access (admins bypass this in policy code). */
    public function accessibleApplications(): BelongsToMany
    {
        return $this->belongsToMany(Application::class, 'application_access');
    }

    public function applicationAccess(): HasMany
    {
        return $this->hasMany(ApplicationAccess::class);
    }

    public function questionnaireResponses(): HasMany
    {
        return $this->hasMany(QuestionnaireResponse::class);
    }

    public function banners(): HasMany
    {
        return $this->hasMany(Banner::class, 'created_by');
    }

    /** Activity entries performed by this user. */
    public function performedActivityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'user_id');
    }

    /** Backward-compatible alias; prefer performedActivityLogs(). */
    public function activityLogs(): HasMany
    {
        return $this->performedActivityLogs();
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * IDs of applications this user is explicitly granted (pegawai).
     * Admins bypass this — callers should check isAdmin() first.
     *
     * @return array<int, int>
     */
    public function accessibleApplicationIds(): array
    {
        return $this->applicationAccess()->pluck('application_id')->all();
    }

    /**
     * Whether this user may access the given application.
     * Admin → always; pegawai → only if an application_access row exists.
     * For rendering many cards at once, prefer the bulk id-set in the
     * controller to avoid a query per application.
     */
    public function canAccessApp(Application $application): bool
    {
        return $this->isAdmin()
            || $this->applicationAccess()->where('application_id', $application->id)->exists();
    }
}
