<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Questionnaire;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use Tests\TestCase;

class QuestionnaireTest extends TestCase
{
    private function user(string $nip): User
    {
        return User::where('nip_nik', $nip)->firstOrFail();
    }

    public function test_only_active_pegawai_receive_unanswered_questionnaire_slides(): void
    {
        $this->withoutVite();

        $pegawai = $this->user('3302010000000001');
        $pegawaiResponse = $this->actingAs($pegawai)->get(route('dashboard'));
        $pegawaiSlides = collect($pegawaiResponse->viewData('popupSlides'));

        $this->assertTrue($pegawaiSlides->contains(fn (array $slide): bool => $slide['type'] === 'banner'));
        $this->assertTrue($pegawaiSlides->contains(fn (array $slide): bool => $slide['type'] === 'questionnaire'));

        $adminResponse = $this->actingAs($this->user('ADMIN001'))->get(route('dashboard'));
        $this->assertCount(0, $adminResponse->viewData('popupSlides'));

        $siti = $this->user('3302010000000002');
        $sitiSlides = collect($this->actingAs($siti)->get(route('dashboard'))->viewData('popupSlides'));
        $this->assertFalse($sitiSlides->contains(fn (array $slide): bool => $slide['type'] === 'questionnaire'));
    }

    public function test_click_is_recorded_once_and_redirects_to_external_form(): void
    {
        $questionnaire = Questionnaire::where('title', 'Survei Kepuasan Portal E-Office')->firstOrFail();
        $pegawai = $this->user('3302010000000001');

        $beforeResponses = QuestionnaireResponse::where('questionnaire_id', $questionnaire->id)
            ->where('user_id', $pegawai->id)
            ->count();
        $beforeLogs = ActivityLog::where('questionnaire_id', $questionnaire->id)
            ->where('user_id', $pegawai->id)
            ->where('activity_type', 'quiz_clicked')
            ->count();

        $this->actingAs($pegawai)
            ->post(route('questionnaire.click', $questionnaire))
            ->assertRedirect($questionnaire->target_url);

        $this->assertSame($beforeResponses + 1, QuestionnaireResponse::where('questionnaire_id', $questionnaire->id)->where('user_id', $pegawai->id)->count());
        $this->assertSame($beforeLogs + 1, ActivityLog::where('questionnaire_id', $questionnaire->id)->where('user_id', $pegawai->id)->where('activity_type', 'quiz_clicked')->count());

        $this->actingAs($pegawai)
            ->post(route('questionnaire.click', $questionnaire))
            ->assertRedirect($questionnaire->target_url);

        $this->assertSame($beforeResponses + 1, QuestionnaireResponse::where('questionnaire_id', $questionnaire->id)->where('user_id', $pegawai->id)->count());
        $this->assertSame($beforeLogs + 1, ActivityLog::where('questionnaire_id', $questionnaire->id)->where('user_id', $pegawai->id)->where('activity_type', 'quiz_clicked')->count());
    }

    public function test_admin_cannot_record_questionnaire_participation(): void
    {
        $questionnaire = Questionnaire::where('title', 'Survei Kepuasan Portal E-Office')->firstOrFail();

        $this->actingAs($this->user('ADMIN001'))
            ->post(route('questionnaire.click', $questionnaire))
            ->assertForbidden();

        $this->assertDatabaseMissing('questionnaire_responses', [
            'questionnaire_id' => $questionnaire->id,
            'user_id' => $this->user('ADMIN001')->id,
        ]);
    }
}
