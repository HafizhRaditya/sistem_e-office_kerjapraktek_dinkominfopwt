<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * `nip_nik` must be unique regardless of letter case.
 *
 * The column carries a plain UNIQUE index, and in PostgreSQL that is
 * case-SENSITIVE: "ADMIN001" and "admin001" are two different values, so the
 * database happily stores both as separate accounts. The two login paths then
 * disagree about what that means — the password form looks the identity up
 * exactly, the Keycloak callback looks it up with lower() on both sides — which
 * is how the same person can be one account to one door and two to the other.
 *
 * Closing that at the database level needs a migration (a unique index on
 * lower(nip_nik), or citext), which is not this change. This rule stops NEW
 * collisions from being created through the admin panel or the console, so the
 * problem cannot grow while that decision is pending.
 */
final class UniqueNipNik implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreUserId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // lower() on both sides rather than ILIKE: the value is user input, and
        // ILIKE would treat any % or _ inside it as a wildcard, turning an exact
        // check into a pattern match. Binding stays parameterised either way.
        $collides = User::whereRaw('lower(nip_nik) = lower(?)', [(string) $value])
            ->when($this->ignoreUserId !== null, fn ($query) => $query->whereKeyNot($this->ignoreUserId))
            ->exists();

        if ($collides) {
            // The first sentence is kept verbatim from the Rule::unique() message
            // this replaces, so existing wording and its test expectation stand.
            // The second explains why "ADMIN001" clashes with "admin001", which
            // is otherwise baffling when the list plainly shows no exact match.
            $fail('NIP/NIK sudah dipakai pengguna lain. Perbandingan mengabaikan huruf besar-kecil.');
        }
    }
}
