<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Questionnaire participation (one click). Event table — no created_at/
 * updated_at (team decision, final). UNIQUE(questionnaire_id, user_id) records
 * an employee exactly once per questionnaire.
 */
class QuestionnaireResponse extends Model
{
    protected $table = 'questionnaire_responses';

    public $timestamps = false;

    protected $fillable = [
        'questionnaire_id',
        'user_id',
        'clicked_at',
    ];

    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
        ];
    }

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaire::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
