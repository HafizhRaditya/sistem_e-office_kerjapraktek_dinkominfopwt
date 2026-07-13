<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-employee access grant to an application. UNIQUE(application_id, user_id).
 * Has created_at/updated_at, so default timestamps apply.
 */
class ApplicationAccess extends Model
{
    protected $table = 'application_access';

    protected $fillable = [
        'application_id',
        'user_id',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
