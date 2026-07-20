<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Questionnaire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuestionnaireController extends Controller
{
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
            ActivityLog::create([
                'user_id' => $user->id,
                'questionnaire_id' => $questionnaire->id,
                'activity_type' => 'quiz_clicked',
                'description' => "Pegawai mengeklik kuisioner: {$questionnaire->title}.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
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
