<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\User;
use Tests\TestCase;

class DashboardViewTest extends TestCase
{
    public function test_dashboard_renders_profile_and_user_statistic_slots(): void
    {
        $this->withoutVite();

        $user = (new User())->forceFill([
            'id' => 7,
            'nip_nik' => '3302010000000001',
            'name' => 'Budi Santoso',
            'email' => 'budi.santoso@banyumaskab.go.id',
            'role' => 'pegawai',
            'is_active' => true,
        ]);

        $user->setRelation('opd', (new Opd())->forceFill([
            'code' => 'SETDA',
            'name' => 'Sekretariat Daerah',
        ]));

        $response = $this->view('dashboard', [
            'apps' => [],
            'user' => $user,
            'userStats' => [
                'accessible_apps' => 3,
                'restricted_apps' => 2,
                'month_visits' => 8,
                'year_visits' => 21,
            ],
        ]);

        $response
            ->assertSee('Profil pengguna')
            ->assertSee('Budi Santoso')
            ->assertSee('Sekretariat Daerah')
            ->assertSee('Statistik aktivitas Anda')
            ->assertSee('Dapat diakses')
            ->assertSee('Akses terbatas')
            ->assertSee('Kunjungan bulan ini')
            ->assertSee('Kunjungan tahun ini')
            ->assertSee('Perlindungan data pribadi')
            ->assertSee('Kontak Dinkominfo')
            ->assertSee('Portal Banyumas')
            ->assertSee('https://dinkominfo.banyumaskab.go.id/page/24768/alamat-dan-kontak', false)
            ->assertDontSee('href="#"', false);

    }
}
