<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Opd;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Admin — Manajemen Pengguna (LANGKAH 4).
 *
 * Runs inside a transaction on the isolated, migrated, and seeded PostgreSQL
 * test database. Test-created accounts use a "UJI" nip_nik prefix for clarity.
 */
class AdminUserManagementTest extends TestCase
{
    protected function tearDown(): void
    {
        User::where('nip_nik', 'like', 'UJI%')->delete();

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::where('nip_nik', 'ADMIN001')->firstOrFail();
    }

    private function pegawai(): User
    {
        return User::where('nip_nik', '3302010000000002')->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Uji Pengguna',
            'nip_nik' => 'UJI001',
            'opd_id' => Opd::orderBy('id')->firstOrFail()->id,
            'role' => 'pegawai',
            'is_active' => '1',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
        ], $overrides);
    }

    public function test_pegawai_is_forbidden_from_user_management(): void
    {
        $this->actingAs($this->pegawai())->get('/admin/pengguna')->assertStatus(403);
    }

    public function test_admin_can_view_the_user_list(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/pengguna')
            ->assertOk()
            ->assertSee('Manajemen Pengguna');
    }

    public function test_admin_can_create_a_user_with_a_working_password(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), $this->payload())
            ->assertRedirect(route('admin.users.index'));

        $user = User::where('nip_nik', 'UJI001')->first();

        $this->assertNotNull($user);
        $this->assertSame('pegawai', $user->role);
        $this->assertTrue($user->is_active);
        // 'hashed' cast must hash exactly once, so the plain password verifies.
        $this->assertTrue(Hash::check('Rahasia123', $user->password));
    }

    public function test_duplicate_nip_nik_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), $this->payload(['nip_nik' => 'ADMIN001']))
            ->assertSessionHasErrors('nip_nik');
    }

    public function test_weak_password_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), $this->payload(['password' => '123', 'password_confirmation' => '123']))
            ->assertSessionHasErrors('password');

        $this->assertNull(User::where('nip_nik', 'UJI001')->first());
    }

    public function test_admin_can_toggle_another_users_status(): void
    {
        $this->actingAs($this->admin())->post(route('admin.users.store'), $this->payload());
        $user = User::where('nip_nik', 'UJI001')->firstOrFail();

        $this->actingAs($this->admin())->patch(route('admin.users.status', $user));
        $this->assertFalse($user->fresh()->is_active);

        $this->actingAs($this->admin())->patch(route('admin.users.status', $user));
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $admin))
            ->assertSessionHasErrors('user');

        $this->assertTrue($admin->fresh()->is_active, 'admin must stay active');
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertSessionHasErrors('user');

        $this->assertNotNull(User::find($admin->id), 'admin must still exist');
    }

    public function test_admin_cannot_demote_their_own_role_or_deactivate_via_the_form(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'nip_nik' => $admin->nip_nik,
            'email' => $admin->email,
            'opd_id' => $admin->opd_id,
            'role' => 'pegawai', // attempt to demote self
            // is_active omitted => attempt to deactivate self
        ])->assertRedirect();

        $admin->refresh();
        $this->assertSame('admin', $admin->role, 'self role must stay admin');
        $this->assertTrue($admin->is_active, 'self must stay active');
    }

    public function test_admin_can_reset_a_user_password_and_it_is_logged(): void
    {
        $this->actingAs($this->admin())->post(route('admin.users.store'), $this->payload());
        $user = User::where('nip_nik', 'UJI001')->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.users.password', $user), [
                'password' => 'SandiBaru9',
                'password_confirmation' => 'SandiBaru9',
            ])
            ->assertRedirect(route('admin.users.edit', $user));

        $this->assertTrue(Hash::check('SandiBaru9', $user->fresh()->password));
        $this->assertTrue(
            ActivityLog::where('user_id', $user->id)->where('activity_type', 'password_changed')->exists(),
            'password reset must be recorded in activity_logs'
        );
    }

    public function test_a_user_who_created_a_questionnaire_cannot_be_deleted(): void
    {
        // ADMIN001 is the seeded questionnaire's created_by (FK is ON DELETE RESTRICT).
        // Act as a *different* admin so the self-guard is not what blocks it.
        $this->actingAs($this->admin())->post(route('admin.users.store'), $this->payload([
            'name' => 'Uji Admin Kedua',
            'nip_nik' => 'UJIADM',
            'role' => 'admin',
        ]));
        $secondAdmin = User::where('nip_nik', 'UJIADM')->firstOrFail();
        $target = $this->admin();

        $this->assertTrue(DB::table('questionnaires')->where('created_by', $target->id)->exists());

        $this->actingAs($secondAdmin)
            ->delete(route('admin.users.destroy', $target))
            ->assertSessionHasErrors('user');

        $this->assertNotNull(User::find($target->id), 'questionnaire creator must not be deleted');
    }

    public function test_admin_can_delete_a_normal_user(): void
    {
        $this->actingAs($this->admin())->post(route('admin.users.store'), $this->payload(['nip_nik' => 'UJIDEL']));
        $user = User::where('nip_nik', 'UJIDEL')->firstOrFail();

        $this->actingAs($this->admin())
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertNull(User::where('nip_nik', 'UJIDEL')->first());
    }
}
