<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EofficeV21Seeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $opds = [
                ['code' => 'DINKOMINFO', 'name' => 'Dinas Komunikasi dan Informatika'],
                ['code' => 'SETDA', 'name' => 'Sekretariat Daerah'],
                ['code' => 'BAPPEDA', 'name' => 'Bappedalitbang'],
                ['code' => 'DINKES', 'name' => 'Dinas Kesehatan'],
                ['code' => 'BKPSDM', 'name' => 'Badan Kepegawaian dan Pengembangan SDM'],
            ];

            foreach ($opds as $opd) {
                DB::table('opds')->updateOrInsert(
                    ['code' => $opd['code']],
                    [
                        'name' => $opd['name'],
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }

            $opdId = fn (string $code): int => (int) DB::table('opds')->where('code', $code)->value('id');

            $users = [
                [
                    'opd_code' => 'DINKOMINFO',
                    'nip_nik' => 'ADMIN001',
                    'name' => 'Admin E-Office',
                    'email' => 'admin@banyumaskab.go.id',
                    'role' => 'admin',
                ],
                [
                    'opd_code' => 'SETDA',
                    'nip_nik' => '3302010000000001',
                    'name' => 'Budi Santoso',
                    'email' => 'budi.santoso@banyumaskab.go.id',
                    'role' => 'pegawai',
                ],
                [
                    'opd_code' => 'DINKOMINFO',
                    'nip_nik' => '3302010000000002',
                    'name' => 'Siti Rahayu',
                    'email' => 'siti.rahayu@banyumaskab.go.id',
                    'role' => 'pegawai',
                ],
                [
                    'opd_code' => 'DINKES',
                    'nip_nik' => '3302010000000003',
                    'name' => 'Agus Prasetyo',
                    'email' => 'agus.prasetyo@banyumaskab.go.id',
                    'role' => 'pegawai',
                ],
            ];

            foreach ($users as $user) {
                DB::table('users')->updateOrInsert(
                    ['nip_nik' => $user['nip_nik']],
                    [
                        'opd_id' => $opdId($user['opd_code']),
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'email_verified_at' => now(),
                        'password' => Hash::make('password'),
                        'role' => $user['role'],
                        'is_active' => true,
                        'last_login_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }

            $userId = fn (string $nipNik): int => (int) DB::table('users')->where('nip_nik', $nipNik)->value('id');

            $applications = [
                [
                    'opd_code' => 'DINKOMINFO',
                    'name' => 'Banyumas Smart City',
                    'slug' => 'banyumas-smart-city',
                    'description' => 'Portal integrasi layanan Smart City Kabupaten Banyumas.',
                    'app_group' => 'smartcity',
                    'category' => 'governance',
                    'is_active' => true,
                    'is_new' => false,
                    'sort_order' => 1,
                    'links' => [
                        ['label' => 'Frontend', 'url' => 'https://smartcity.banyumaskab.go.id', 'is_active' => true, 'sort_order' => 1],
                        ['label' => 'Backend', 'url' => 'https://smartcity.banyumaskab.go.id/admin', 'is_active' => true, 'sort_order' => 2],
                    ],
                    'access_nip_nik' => ['3302010000000001', '3302010000000002'],
                ],
                [
                    'opd_code' => 'DINKES',
                    'name' => 'SIMPUS',
                    'slug' => 'simpus',
                    'description' => 'Sistem informasi manajemen puskesmas.',
                    'app_group' => 'spbe',
                    'category' => 'kesehatan',
                    'icon' => 'images/applications/simpus.webp',
                    'is_active' => true,
                    'is_new' => false,
                    'sort_order' => 2,
                    'links' => [
                        ['label' => 'Frontend', 'url' => 'https://simpus.banyumaskab.go.id', 'is_active' => true, 'sort_order' => 1],
                        ['label' => 'Backend', 'url' => 'https://simpus.banyumaskab.go.id/admin', 'is_active' => true, 'sort_order' => 2],
                    ],
                    'access_nip_nik' => ['3302010000000003'],
                ],
                [
                    'opd_code' => 'BAPPEDA',
                    'name' => 'E-Planning',
                    'slug' => 'e-planning',
                    'description' => 'Aplikasi perencanaan dan pemantauan program daerah.',
                    'app_group' => 'spbe',
                    'category' => 'rencana',
                    'is_active' => true,
                    'is_new' => false,
                    'sort_order' => 3,
                    'links' => [
                        ['label' => 'Frontend', 'url' => 'https://eplanning.banyumaskab.go.id', 'is_active' => true, 'sort_order' => 1],
                    ],
                    'access_nip_nik' => ['3302010000000002'],
                ],
                [
                    'opd_code' => 'SETDA',
                    'name' => 'Agenda Pimpinan',
                    'slug' => 'agenda-pimpinan',
                    'description' => 'Informasi agenda pimpinan dan koordinasi perangkat daerah.',
                    'app_group' => 'tools',
                    'category' => 'umum',
                    'is_active' => false,
                    'is_new' => false,
                    'sort_order' => 4,
                    'links' => [
                        ['label' => 'Frontend', 'url' => 'https://agenda.banyumaskab.go.id', 'is_active' => true, 'sort_order' => 1],
                    ],
                    'access_nip_nik' => ['3302010000000001'],
                ],
                [
                    'opd_code' => 'DINKOMINFO',
                    'name' => 'Data Hub Banyumas',
                    'slug' => 'data-hub-banyumas',
                    'description' => 'Katalog data dan layanan integrasi data lintas OPD.',
                    'app_group' => 'smartcity',
                    'category' => 'data',
                    'is_active' => true,
                    'is_new' => true,
                    'sort_order' => 5,
                    'links' => [
                        ['label' => 'Frontend', 'url' => 'https://data.banyumaskab.go.id', 'is_active' => true, 'sort_order' => 1],
                        ['label' => 'Backend V2', 'url' => 'https://data.banyumaskab.go.id/admin-v2', 'is_active' => false, 'sort_order' => 2],
                    ],
                    'access_nip_nik' => ['3302010000000001', '3302010000000002'],
                ],
            ];

            foreach ($applications as $application) {
                DB::table('applications')->updateOrInsert(
                    ['slug' => $application['slug']],
                    [
                        'opd_id' => $opdId($application['opd_code']),
                        'name' => $application['name'],
                        'description' => $application['description'],
                        'icon' => $application['icon'] ?? null,
                        'app_group' => $application['app_group'],
                        'category' => $application['category'],
                        'is_active' => $application['is_active'],
                        'is_new' => $application['is_new'],
                        'sort_order' => $application['sort_order'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );

                $applicationId = (int) DB::table('applications')->where('slug', $application['slug'])->value('id');

                foreach ($application['links'] as $link) {
                    DB::table('application_links')->updateOrInsert(
                        [
                            'application_id' => $applicationId,
                            'label' => $link['label'],
                        ],
                        [
                            'url' => $link['url'],
                            'is_active' => $link['is_active'],
                            'sort_order' => $link['sort_order'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                }

                foreach ($application['access_nip_nik'] as $nipNik) {
                    DB::table('application_access')->updateOrInsert(
                        [
                            'application_id' => $applicationId,
                            'user_id' => $userId($nipNik),
                        ],
                        [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                }
            }

            DB::table('questionnaires')->updateOrInsert(
                ['title' => 'Survei Kepuasan Portal E-Office'],
                [
                    'created_by' => $userId('ADMIN001'),
                    'description' => 'Bantu kami meningkatkan kualitas portal E-Office Kabupaten Banyumas.',
                    'banner_image' => null,
                    'target_url' => 'https://forms.gle/example',
                    'is_active' => true,
                    'starts_at' => now()->subDay(),
                    'ends_at' => now()->addDays(30),
                    'sort_order' => 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $questionnaireId = (int) DB::table('questionnaires')
                ->where('title', 'Survei Kepuasan Portal E-Office')
                ->value('id');

            DB::table('questionnaire_responses')->updateOrInsert(
                [
                    'questionnaire_id' => $questionnaireId,
                    'user_id' => $userId('3302010000000002'),
                ],
                [
                    'clicked_at' => now()->subHours(2),

                ],
            );

            DB::table('banners')->updateOrInsert(
                ['title' => 'Selamat Datang di Portal E-Office'],
                [
                    'created_by' => $userId('ADMIN001'),
                    'description' => 'Informasi portal dan layanan digital Kabupaten Banyumas.',
                    'image_path' => null,
                    'target_url' => null,
                    'is_active' => true,
                    'starts_at' => now()->subDay(),
                    'ends_at' => now()->addDays(30),
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $applicationId = (int) DB::table('applications')->where('slug', 'banyumas-smart-city')->value('id');
            $linkId = (int) DB::table('application_links')
                ->where('application_id', $applicationId)
                ->where('label', 'Frontend')
                ->value('id');

            DB::table('application_visits')->updateOrInsert(
                [
                    'application_link_id' => $linkId,
                    'user_id' => $userId('3302010000000001'),
                    'visit_date' => now()->toDateString(),
                ],
                [
                    'application_id' => $applicationId,
                    'visited_at' => now(),

                ],
            );

            DB::table('activity_logs')->where('user_agent', 'Seeder')->delete();

            DB::table('activity_logs')->insert([
                [
                    'user_id' => $userId('3302010000000001'),
                    'application_id' => $applicationId,
                    'questionnaire_id' => null,
                    'activity_type' => 'app_launched',
                    'description' => 'Seeder sample: pegawai membuka Banyumas Smart City.',
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Seeder',
                    'created_at' => now(),

                ],
                [
                    'user_id' => $userId('3302010000000002'),
                    'application_id' => null,
                    'questionnaire_id' => $questionnaireId,
                    'activity_type' => 'quiz_clicked',
                    'description' => 'Seeder sample: pegawai mengeklik tombol Isi Kuisioner.',
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Seeder',
                    'created_at' => now(),

                ],
            ]);
        });
    }
}
