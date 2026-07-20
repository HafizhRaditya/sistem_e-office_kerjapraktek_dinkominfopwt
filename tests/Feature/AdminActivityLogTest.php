<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Admin — Log Aktivitas viewer (LANGKAH 4 / FR-A12).
 * Read-only, paginated, filtered by user / activity_type / date range.
 */
class AdminActivityLogTest extends TestCase
{
    private function admin(): User
    {
        return User::where('nip_nik', 'ADMIN001')->firstOrFail();
    }

    private function pegawai(): User
    {
        return User::where('nip_nik', '3302010000000002')->firstOrFail();
    }

    public function test_pegawai_is_forbidden_from_the_activity_log(): void
    {
        $this->actingAs($this->pegawai())->get('/admin/log-aktivitas')->assertStatus(403);
    }

    public function test_admin_can_view_the_activity_log(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/log-aktivitas')
            ->assertOk()
            ->assertSee('Log Aktivitas');
    }

    public function test_activity_type_filter_narrows_the_result(): void
    {
        // The seeder leaves an app_launched row; filtering by a type that exists
        // must not error and must keep only that type on the page.
        $response = $this->actingAs($this->admin())
            ->get(route('admin.logs.index', ['type' => 'app_launched']))
            ->assertOk();

        $logs = $response->viewData('logs');
        foreach ($logs as $log) {
            $this->assertSame('app_launched', $log->activity_type);
        }
    }

    public function test_user_filter_narrows_the_result(): void
    {
        $budi = User::where('nip_nik', '3302010000000001')->firstOrFail();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.logs.index', ['user' => $budi->id]))
            ->assertOk();

        foreach ($response->viewData('logs') as $log) {
            $this->assertSame($budi->id, $log->user_id);
        }
    }

    public function test_date_range_filter_is_accepted_and_invalid_dates_are_rejected(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.logs.index', ['from' => now()->subDays(7)->toDateString(), 'to' => now()->toDateString()]))
            ->assertOk();

        $this->actingAs($this->admin())
            ->get(route('admin.logs.index', ['from' => 'bukan-tanggal']))
            ->assertSessionHasErrors('from');
    }
}
