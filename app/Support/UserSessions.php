<?php

namespace App\Support;

use App\Services\KeycloakOidcService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ending a user's server-side sessions.
 *
 * A password change used to leave every other session that account had open
 * still signed in. That is backwards: resetting a password is what an admin
 * reaches for when they suspect an account is compromised, and it was the one
 * action that did NOT evict the intruder.
 *
 * This reads the `sessions` table directly rather than going through the guard,
 * because the sessions being ended belong to other browsers — there is no way to
 * reach them from the current request.
 */
final class UserSessions
{
    /**
     * End this user's sessions, optionally sparing one (normally the caller's).
     *
     * Returns the number of sessions ended, so callers can report it.
     */
    public static function purge(int $userId, ?string $exceptSessionId = null): int
    {
        $driver = config('session.driver');

        if ($driver !== 'database') {
            // Deliberately loud. Every other driver stores sessions somewhere
            // this cannot reach, so the eviction would simply not happen —
            // and a security control that quietly does nothing is worse than
            // one that is absent, because nobody goes looking for it.
            Log::warning(
                'UserSessions::purge dilewati: SESSION_DRIVER bukan "database", sesi lain TIDAK dicabut.',
                ['driver' => $driver, 'user_id' => $userId],
            );

            return 0;
        }

        $query = DB::table(config('session.table', 'sessions'))->where('user_id', $userId);

        if ($exceptSessionId !== null) {
            $query->where('id', '!=', $exceptSessionId);
        }

        return $query->delete();
    }

    /**
     * End the session belonging to one Keycloak session id.
     *
     * Back-channel logout names an OP session (`sid`), not a portal session, so
     * the link has to be read back out of the session payload where the SSO
     * callback stored it. Only this user's rows are examined — a handful at
     * most — rather than the whole table.
     *
     * Returns the number of sessions ended, so the caller can tell the
     * difference between "logged someone out" and "found nothing to do". That
     * distinction matters: back-channel logout failing silently would look
     * exactly like it working.
     */
    public static function purgeByKeycloakSid(int $userId, string $sid): int
    {
        $driver = config('session.driver');

        if ($driver !== 'database') {
            Log::warning(
                'UserSessions::purgeByKeycloakSid dilewati: SESSION_DRIVER bukan "database".',
                ['driver' => $driver, 'user_id' => $userId],
            );

            return 0;
        }

        $table = config('session.table', 'sessions');

        $ids = DB::table($table)
            ->where('user_id', $userId)
            ->get(['id', 'payload'])
            ->filter(fn (object $row): bool => self::payloadSid($row->payload) === $sid)
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return 0;
        }

        return DB::table($table)->whereIn('id', $ids)->delete();
    }

    /**
     * Read the stored Keycloak `sid` out of a raw session payload.
     *
     * The payload is base64 of whatever `session.serialization` selects. This
     * project sets it to 'json', so the encoding is base64(json_encode(...)) —
     * NOT serialize(), which is what this method originally assumed. That
     * mistake made the method return null for every real session, so
     * back-channel logout quietly ended nothing at all while reporting success.
     *
     * unserialize() is still supported for the 'php' setting and for sessions
     * written before a configuration change. allowed_classes stays false there:
     * nothing here needs objects, and it keeps object instantiation out of a
     * path whose input ultimately traces back to an external request.
     */
    private static function payloadSid(?string $payload): ?string
    {
        if (blank($payload)) {
            return null;
        }

        $raw = base64_decode($payload, true);

        if ($raw === false) {
            return null;
        }

        /*
        * Laravel pada proyek ini menggunakan serialisasi JSON.
        * Tetap mendukung format PHP apabila konfigurasi diubah
        * atau terdapat sesi lama dengan format tersebut.
        */
        $attributes = config('session.serialization', 'json') === 'json'
            ? json_decode($raw, true)
            : @unserialize($raw, ['allowed_classes' => false]);

        if (! is_array($attributes)) {
            return null;
        }

        $sid = $attributes[KeycloakOidcService::SESSION_SID] ?? null;

        return is_string($sid) && $sid !== '' ? $sid : null;
    }
}
