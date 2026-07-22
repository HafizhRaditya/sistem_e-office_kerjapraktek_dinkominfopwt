<?php

namespace App\Models;

use App\Support\ActivityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail. `user_id` is the actor; subject_* stores the affected record.
 * Event table — no updated_at, only created_at.
 */
class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'application_id',
        'questionnaire_id',
        'subject_type',
        'subject_id',
        'subject_label',
        'activity_type',
        'description',
        'properties',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** The authenticated user who performed the action. */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Backward-compatible alias; prefer actor() in new code. */
    public function user(): BelongsTo
    {
        return $this->actor();
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaire::class);
    }

    public function getActivityLabelAttribute(): string
    {
        return ActivityType::label($this->activity_type);
    }
}
