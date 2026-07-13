<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Valid application visit. Event table — no created_at/updated_at (team
 * decision, final). Daily uniqueness per (link + user + date) is enforced by
 * the uq_visit_daily unique index at the DB level.
 */
class ApplicationVisit extends Model
{
    protected $table = 'application_visits';

    public $timestamps = false;

    protected $fillable = [
        'application_id',
        'application_link_id',
        'user_id',
        'visit_date',
        'visited_at',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'visited_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(ApplicationLink::class, 'application_link_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
