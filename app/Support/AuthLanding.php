<?php

namespace App\Support;

use App\Models\User;

/**
 * Where a user lands after a successful login (FR-A01).
 *
 * Shared by both login paths — the NIP/NIK form and the Keycloak SSO callback —
 * so the two can never drift into sending the same role to different places.
 */
final class AuthLanding
{
    public static function homeFor(User $user): string
    {
        return $user->isAdmin()
            ? route('admin.akses.index')
            : route('dashboard');
    }
}
