<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\Banner;
use App\Models\Opd;
use App\Models\Questionnaire;
use App\Models\User;
use App\Support\ActivityType;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminAuditTrailTest extends TestCase
{
    private function admin(): User
    {
        return User::where('nip_nik', 'ADMIN001')->firstOrFail();
    }

    private function pegawai(): User
    {
        return User::where('nip_nik', '3302010000000003')->firstOrFail();
    }

    public function test_activity_log_schema_separates_actor_subject_and_properties(): void
    {
        $this->assertTrue(Schema::hasColumns('activity_logs', [
            'user_id', 'subject_type', 'subject_id', 'subject_label', 'properties',
        ]));
    }

    public function test_user_admin_actions_record_actor_subject_and_non_sensitive_changes(): void
    {
        $admin = $this->admin();
        $opd = Opd::orderBy('id')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Pengguna Audit',
            'nip_nik' => 'AUDIT001',
            'email' => 'audit@example.test',
            'opd_id' => $opd->id,
            'role' => 'pegawai',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
            'is_active' => '1',
        ])->assertRedirect();

        $user = User::where('nip_nik', 'AUDIT001')->firstOrFail();
        $created = ActivityLog::where('activity_type', ActivityType::USER_CREATED)
            ->where('subject_id', $user->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($admin->id, $created->user_id);
        $this->assertSame('user', $created->subject_type);
        $this->assertSame('Pengguna Audit', $created->subject_label);
        $this->assertArrayNotHasKey('password', $created->properties['after']);

        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => 'Pengguna Audit Diubah',
            'nip_nik' => $user->nip_nik,
            'email' => 'audit-baru@example.test',
            'opd_id' => $user->opd_id,
            'role' => 'pegawai',
            'is_active' => '1',
        ])->assertRedirect();

        $updated = ActivityLog::where('activity_type', ActivityType::USER_UPDATED)
            ->where('subject_id', $user->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('Pengguna Audit', $updated->properties['before']['name']);
        $this->assertSame('Pengguna Audit Diubah', $updated->properties['after']['name']);

        $this->actingAs($admin)
            ->put(route('admin.users.password', $user), [
                'password' => 'SandiBaru9',
                'password_confirmation' => 'SandiBaru9',
            ])
            ->assertRedirect();

        $reset = ActivityLog::where('activity_type', ActivityType::PASSWORD_RESET)
            ->where('subject_id', $user->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($admin->id, $reset->user_id);
        $this->assertNull($reset->properties);
        $this->assertStringNotContainsString('SandiBaru9', $reset->description);
        $this->assertTrue(Hash::check('SandiBaru9', $user->fresh()->password));
    }

    public function test_application_link_and_access_changes_are_audited(): void
    {
        $admin = $this->admin();
        $opd = Opd::orderBy('id')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.aplikasi.store'), [
            'name' => 'Aplikasi Audit',
            'opd_id' => $opd->id,
            'slug' => 'aplikasi-audit',
            'description' => 'Untuk uji audit.',
            'app_group' => 'spbe',
            'category' => 'data',
            'sort_order' => 900,
            'is_active' => '1',
        ])->assertRedirect();

        $application = Application::where('slug', 'aplikasi-audit')->firstOrFail();
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'application_id' => $application->id,
            'subject_type' => 'application',
            'subject_id' => $application->id,
            'activity_type' => ActivityType::APPLICATION_CREATED,
        ]);

        $this->actingAs($admin)->post(route('admin.aplikasi.link.store', $application), [
            'label' => 'Frontend Audit',
            'url' => 'https://contoh.banyumaskab.go.id/audit',
            'sort_order' => 0,
            'is_active' => '1',
        ])->assertRedirect();

        $link = $application->links()->where('label', 'Frontend Audit')->firstOrFail();
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'application_id' => $application->id,
            'subject_type' => 'application_link',
            'subject_id' => $link->id,
            'activity_type' => ActivityType::APPLICATION_LINK_CREATED,
        ]);

        $pegawai = $this->pegawai();
        $this->actingAs($admin)->put(route('admin.akses.update', $pegawai), [
            'access' => [$application->id],
        ])->assertRedirect();

        $accessLog = ActivityLog::where('activity_type', ActivityType::ACCESS_UPDATED)
            ->where('subject_type', 'user')
            ->where('subject_id', $pegawai->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($admin->id, $accessLog->user_id);
        $this->assertSame($application->id, $accessLog->properties['added'][0]['id']);
    }

    public function test_deleted_banner_and_questionnaire_keep_an_object_snapshot_in_the_log(): void
    {
        $admin = $this->admin();

        $banner = Banner::create([
            'created_by' => $admin->id,
            'title' => 'Banner Audit Hapus',
            'description' => null,
            'image_path' => null,
            'target_url' => null,
            'is_active' => false,
            'starts_at' => null,
            'ends_at' => null,
            'sort_order' => 500,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.banners.destroy', $banner))
            ->assertRedirect();

        $this->assertNull(Banner::find($banner->id));
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'subject_type' => 'banner',
            'subject_id' => $banner->id,
            'subject_label' => 'Banner Audit Hapus',
            'activity_type' => ActivityType::BANNER_DELETED,
        ]);

        $questionnaire = Questionnaire::create([
            'created_by' => $admin->id,
            'title' => 'Kuisioner Audit Hapus',
            'description' => null,
            'banner_image' => null,
            'target_url' => 'https://forms.gle/audit-delete',
            'is_active' => false,
            'starts_at' => null,
            'ends_at' => null,
            'sort_order' => 500,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.questionnaires.destroy', $questionnaire))
            ->assertRedirect();

        $this->assertNull(Questionnaire::find($questionnaire->id));
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'questionnaire_id' => null,
            'subject_type' => 'questionnaire',
            'subject_id' => $questionnaire->id,
            'subject_label' => 'Kuisioner Audit Hapus',
            'activity_type' => ActivityType::QUESTIONNAIRE_DELETED,
        ]);
    }

    public function test_log_page_uses_actor_and_object_language(): void
    {
        $this->withoutVite();

        $this->actingAs($this->admin())
            ->get(route('admin.logs.index'))
            ->assertOk()
            ->assertSee('Pelaku')
            ->assertSee('Objek')
            ->assertSee('Login berhasil')
            ->assertDontSee('Semua pengguna');
    }
}
