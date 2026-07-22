<?php

namespace App\Http\Controllers;

use App\Models\Questionnaire;
use App\Services\ActivityLogger;
use App\Support\ActivityType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuestionnaireController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function click(Request $request, Questionnaire $questionnaire)
    {
        $user = $request->user();

        abort_unless($user->is_active && $user->role === 'pegawai', 403);
        abort_unless($this->isCurrentlyActive($questionnaire), 404);

        $scheme = strtolower((string) parse_url($questionnaire->target_url, PHP_URL_SCHEME));
        abort_unless(in_array($scheme, ['http', 'https'], true), 404);

        $inserted = DB::table('questionnaire_responses')->insertOrIgnore([
            'questionnaire_id' => $questionnaire->id,
            'user_id' => $user->id,
            'clicked_at' => now(),
        ]);

        if ($inserted === 1) {
            $this->activityLogger->record(
                $request,
                ActivityType::QUIZ_CLICKED,
                "Pegawai mengeklik kuisioner: {$questionnaire->title}.",
                subject: $questionnaire,
            );
        }

        return redirect()->away($questionnaire->target_url);
    }

    private function isCurrentlyActive(Questionnaire $questionnaire): bool
    {
        $now = now();

        return $questionnaire->is_active
            && (! $questionnaire->starts_at || $questionnaire->starts_at->lte($now))
            && (! $questionnaire->ends_at || $questionnaire->ends_at->gte($now));
    }
}
