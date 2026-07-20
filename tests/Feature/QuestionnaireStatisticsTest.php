<?php

namespace Tests\Feature;

use App\Models\Questionnaire;
use App\Models\User;
use Tests\TestCase;

class QuestionnaireStatisticsTest extends TestCase
{
    private function user(string $nip): User
    {
        return User::where('nip_nik', $nip)->firstOrFail();
    }

    public function test_admin_sees_accurate_questionnaire_statistics(): void
    {
        $this->withoutVite();

        $questionnaire = Questionnaire::where('title', 'Survei Kepuasan Portal E-Office')->firstOrFail();
        $response = $this->actingAs($this->user('ADMIN001'))
            ->get(route('admin.questionnaires.statistics', ['questionnaire' => $questionnaire->id]));

        $response->assertOk()->assertSee('Statistik Kuisioner');

        $metrics = $response->viewData('metrics');
        $this->assertSame(3, $metrics['total_target']);
        $this->assertSame(1, $metrics['active_responded']);
        $this->assertSame(2, $metrics['active_pending']);
        $this->assertSame(33.3, $metrics['percentage']);
        $this->assertSame(1, $metrics['total_clicks']);

        $opdStats = collect($response->viewData('opdStats'))->keyBy(fn (array $stat): string => $stat['opd']->code);
        $this->assertSame(1, $opdStats['DINKOMINFO']['responded']);
        $this->assertSame(1, $opdStats['DINKES']['pending']);
        $this->assertSame(1, $opdStats['SETDA']['pending']);
    }

    public function test_pegawai_cannot_open_questionnaire_statistics(): void
    {
        $this->actingAs($this->user('3302010000000001'))
            ->get(route('admin.questionnaires.statistics'))
            ->assertForbidden();
    }

    public function test_status_filter_returns_only_responded_active_employees(): void
    {
        $this->withoutVite();

        $response = $this->actingAs($this->user('ADMIN001'))
            ->get(route('admin.questionnaires.statistics', ['status' => 'responded']));

        $employees = collect($response->viewData('employees')->items());
        $this->assertCount(1, $employees);
        $this->assertSame('3302010000000002', $employees->first()->nip_nik);
    }
}
