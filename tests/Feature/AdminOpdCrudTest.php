<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\Opd;
use App\Models\User;
use App\Support\ActivityType;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminOpdCrudTest extends TestCase
{
    private function admin(): User
    {
        return User::where('nip_nik', 'ADMIN001')->firstOrFail();
    }

    private function pegawai(): User
    {
        return User::where('role', 'pegawai')->firstOrFail();
    }

    public function test_pegawai_is_forbidden_from_opd_management(): void
    {
        $opd = Opd::orderBy('id')->firstOrFail();

        foreach ([
            route('admin.opds.index'),
            route('admin.opds.create'),
            route('admin.opds.edit', $opd),
        ] as $url) {
            $this->actingAs($this->pegawai())->get($url)->assertForbidden();
        }
    }

    public function test_admin_can_view_opd_list_and_create_form(): void
    {
        $this->withoutVite();

        $this->actingAs($this->admin())
            ->get(route('admin.opds.index'))
            ->assertOk()
            ->assertSee('Manajemen OPD')
            ->assertSee('Tambah OPD');

        $this->actingAs($this->admin())
            ->get(route('admin.opds.create'))
            ->assertOk()
            ->assertSee('Kode OPD')
            ->assertSee('Nama OPD');
    }

    public function test_admin_can_create_opd_and_code_is_normalized_and_audited(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.opds.store'), [
                'code' => ' disarpus ',
                'name' => ' Dinas Arsip dan Perpustakaan ',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.opds.index'));

        $opd = Opd::where('code', 'DISARPUS')->firstOrFail();

        $this->assertSame('Dinas Arsip dan Perpustakaan', $opd->name);
        $this->assertTrue($opd->is_active);

        $log = ActivityLog::where('activity_type', ActivityType::OPD_CREATED)
            ->where('subject_type', 'opd')
            ->where('subject_id', $opd->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($opd->name, $log->subject_label);
        $this->assertSame('DISARPUS', $log->properties['after']['code']);
    }

    public function test_duplicate_and_invalid_opd_code_are_rejected(): void
    {
        $existing = Opd::orderBy('id')->firstOrFail();

        $this->actingAs($this->admin())
            ->from(route('admin.opds.create'))
            ->post(route('admin.opds.store'), [
                'code' => strtolower($existing->code),
                'name' => 'Duplikat',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.opds.create'))
            ->assertSessionHasErrors('code');

        $this->actingAs($this->admin())
            ->from(route('admin.opds.create'))
            ->post(route('admin.opds.store'), [
                'code' => '../RAHASIA',
                'name' => 'Kode Tidak Aman',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.opds.create'))
            ->assertSessionHasErrors('code');
    }

    public function test_admin_can_update_and_toggle_opd_without_losing_relations(): void
    {
        $admin = $this->admin();
        $opd = Opd::create([
            'code' => 'UJI-OPD',
            'name' => 'OPD Uji Histori',
            'is_active' => true,
        ]);

        $user = User::create([
            'opd_id' => $opd->id,
            'nip_nik' => 'UJIOPD001',
            'name' => 'Pegawai OPD Uji',
            'password' => 'Password9',
            'role' => 'pegawai',
            'is_active' => true,
        ]);

        $application = Application::create([
            'opd_id' => $opd->id,
            'name' => 'Aplikasi OPD Uji',
            'slug' => 'aplikasi-opd-uji',
            'description' => null,
            'icon' => null,
            'app_group' => 'spbe',
            'category' => 'data',
            'is_active' => true,
            'is_new' => false,
            'sort_order' => 999,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.opds.update', $opd), [
                'code' => 'uji-opd-baru',
                'name' => 'OPD Uji Histori Baru',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.opds.edit', $opd));

        $opd->refresh();
        $this->assertSame('UJI-OPD-BARU', $opd->code);
        $this->assertSame('OPD Uji Histori Baru', $opd->name);

        $this->actingAs($admin)
            ->patch(route('admin.opds.status', $opd))
            ->assertRedirect();

        $this->assertFalse($opd->fresh()->is_active);
        $this->assertNotNull(User::find($user->id));
        $this->assertNotNull(Application::find($application->id));
        $this->assertSame($opd->id, $user->fresh()->opd_id);
        $this->assertSame($opd->id, $application->fresh()->opd_id);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'subject_type' => 'opd',
            'subject_id' => $opd->id,
            'activity_type' => ActivityType::OPD_DEACTIVATED,
        ]);

        $this->actingAs($admin)->patch(route('admin.opds.status', $opd))->assertRedirect();
        $this->assertTrue($opd->fresh()->is_active);
    }

    public function test_inactive_opd_cannot_be_assigned_to_new_users_or_applications(): void
    {
        $inactive = Opd::create([
            'code' => 'OPD-NONAKTIF',
            'name' => 'OPD Nonaktif untuk Uji',
            'is_active' => false,
        ]);

        $this->withoutVite();

        $this->actingAs($this->admin())
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertDontSee($inactive->name);

        $this->actingAs($this->admin())
            ->get(route('admin.aplikasi.create'))
            ->assertOk()
            ->assertDontSee($inactive->name);

        $this->actingAs($this->admin())
            ->from(route('admin.users.create'))
            ->post(route('admin.users.store'), [
                'name' => 'Pegawai OPD Nonaktif',
                'nip_nik' => 'OPDNONAKTIF001',
                'email' => null,
                'opd_id' => $inactive->id,
                'role' => 'pegawai',
                'password' => 'Password9',
                'password_confirmation' => 'Password9',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.users.create'))
            ->assertSessionHasErrors('opd_id');

        $this->actingAs($this->admin())
            ->from(route('admin.aplikasi.create'))
            ->post(route('admin.aplikasi.store'), [
                'name' => 'Aplikasi OPD Nonaktif',
                'opd_id' => $inactive->id,
                'slug' => 'aplikasi-opd-nonaktif',
                'description' => null,
                'app_group' => 'spbe',
                'category' => 'data',
                'sort_order' => 999,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.aplikasi.create'))
            ->assertSessionHasErrors('opd_id');
    }

    public function test_opd_has_no_permanent_delete_route_or_delete_action(): void
    {
        $this->withoutVite();
        $opd = Opd::orderBy('id')->firstOrFail();

        $this->assertFalse(Route::has('admin.opds.destroy'));

        $this->actingAs($this->admin())
            ->get(route('admin.opds.edit', $opd))
            ->assertOk()
            ->assertSee('tidak dapat dihapus permanen')
            ->assertDontSee('Hapus OPD');
    }
}
