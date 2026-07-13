<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Outbound launch button of an application (BACKEND | FRONTEND | ...).
 */
class ApplicationLink extends Model
{
    protected $table = 'application_links';

    protected $fillable = [
        'application_id',
        'label',
        'url',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(ApplicationVisit::class);
    }
}
