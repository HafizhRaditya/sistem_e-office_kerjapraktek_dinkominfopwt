<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationAccess;
use App\Models\User;
use Tests\TestCase;

/**
 * Admin — Ringkasan (overview page).
 *
 * Guards two things: the page is admin-only like the rest of the panel, and its
 * scope stays inside the auth/access-control module. Questionnaire and banner
 * metrics belong to the dashboard module's own page, so a stray figure creeping
 * in here should fail the build rather than be noticed at the demo.
 */
class AdminOverviewTest extends TestCase
{
    private function user(string $nip): User
    {
        return User::where('nip_nik', $nip)->firstOrFail();
    }

    public function test_admin_can_open_the_overview(): void
    {
        $this->actingAs($this->user('ADMIN001'))
            ->get(route('admin.ringkasan'))
            ->assertOk()
            ->assertSee('Ringkasan')
            ->assertSee('Sebaran Pengguna per OPD')
            ->assertSee('Cakupan Hak Akses');
    }

    public function test_pegawai_is_forbidden_with_403(): void
    {
        $this->actingAs($this->user('3302010000000002'))
            ->get(route('admin.ringkasan'))
            ->assertStatus(403);
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get(route('admin.ringkasan'))->assertRedirect(route('login'));
    }

    public function test_the_figures_match_the_database(): void
    {
        $expectedAdmins = User::where('role', 'admin')->count();
        $expectedPegawai = User::where('role', 'pegawai')->count();
        $activeApps = Application::where('is_active', true)->count();
        $inactiveApps = Application::where('is_active', false)->count();
        $expectedGrants = ApplicationAccess::count();

        $html = $this->actingAs($this->user('ADMIN001'))
            ->get(route('admin.ringkasan'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString("{$expectedAdmins} admin · {$expectedPegawai} pegawai", $html);
        $this->assertStringContainsString("{$activeApps} aktif · {$inactiveApps} nonaktif", $html);
        $this->assertStringContainsString("{$expectedGrants}", $html);
        $this->assertStringContainsString(User::where('role', 'pegawai')->whereHas('applicationAccess')->count().' pegawai punya akses', $html);
    }

    /**
     * The counts must be computed, not hard-coded: deactivating a user has to
     * move the figures on the next request.
     */
    public function test_the_figures_react_to_a_change(): void
    {
        $siti = $this->user('3302010000000002');

        $before = $this->actingAs($this->user('ADMIN001'))
            ->get(route('admin.ringkasan'))->getContent();

        $siti->update(['is_active' => false]);

        $after = $this->actingAs($this->user('ADMIN001'))
            ->get(route('admin.ringkasan'))->getContent();

        $this->assertNotSame($before, $after, 'angka ringkasan harus dihitung dari DB, bukan statis');
        $this->assertStringContainsString('1 nonaktif', $after);
    }

    /**
     * Module boundary: this page belongs to HNR's module, so it must not report
     * the dashboard module's questionnaire/banner statistics. A pointer to that
     * page in prose is fine; figures are not.
     */
    public function test_it_does_not_report_questionnaire_or_banner_statistics(): void
    {
        $html = $this->actingAs($this->user('ADMIN001'))
            ->get(route('admin.ringkasan'))
            ->assertOk()
            ->getContent();

        foreach (['responden', 'partisipan', 'Total Kuisioner', 'Total Banner'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $html,
                "halaman ini tidak boleh melaporkan metrik milik modul rekan tim: \"{$forbidden}\"");
        }
    }

    public function test_the_sidebar_points_at_the_overview_not_the_portal_dashboard(): void
    {
        $html = $this->actingAs($this->user('ADMIN001'))
            ->get(route('admin.ringkasan'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('admin.ringkasan'), $html);

        // The old sidebar entry sent admins out to the employee portal; the panel
        // should no longer offer that as its first item.
        $this->assertStringNotContainsString('>Dashboard', $html,
            'item sidebar "Dashboard" harus sudah diganti menjadi "Ringkasan"');
    }
}
