<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Standalone dashboard banner. The target URL is optional so a banner may
 * be informational or link to an external page.
 */
class Banner extends Model
{
    protected $table = 'banners';

    protected $fillable = [
        'created_by',
        'title',
        'description',
        'image_path',
        'target_url',
        'is_active',
        'starts_at',
        'ends_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
