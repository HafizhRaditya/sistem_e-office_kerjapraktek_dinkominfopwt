<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Delegates to the project seeder. The default Laravel UserFactory is NOT
     * used: our `users` table requires opd_id + nip_nik, which that factory
     * does not provide, so `migrate --seed` would fail on it.
     */
    public function run(): void
    {
        $this->call(EofficeV21Seeder::class);
    }
}
