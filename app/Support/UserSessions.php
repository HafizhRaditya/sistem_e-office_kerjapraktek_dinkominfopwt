<?php

namespace App\Support;

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
}
