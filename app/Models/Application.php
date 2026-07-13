<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Application launcher entry (portal only — the target apps live elsewhere).
 */
class Application extends Model
{
    protected $table = 'applications';

    protected $fillable = [
        'opd_id',
        'name',
        'slug',
        'description',
        'icon',
        'app_group',
        'category',
        'is_active',
        'is_new',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_new' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(ApplicationLink::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(ApplicationVisit::class);
    }

    /** Users granted access to this application. */
    public function grantedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'application_access');
    }
}
