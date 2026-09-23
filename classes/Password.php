<?php
/**
 * Password hashing and verification.
 *
 * A class of its own with no side effects. The other classes need these two
 * functions and cannot get them from `classes/Login.php`, which runs a request
 * router the moment it is loaded.
 */

class Password
{
    /** Explicit so the cost can be raised later without hunting for it. */
    private const OPTIONS = ['cost' => 12];

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, self::OPTIONS);
    }

    /**
     * Checks a password against a stored value in either format.
     *
     * Accounts created before the switch to bcrypt hold a 32-character MD5
     * hash. Those still authenticate, and callers that can write back use
     * needsUpgrade() to replace them.
     */
    public static function verify(string $password, string $stored): bool
    {
        if ($stored === '') {
            return false;
        }

        if (self::isLegacy($stored)) {
            // hash_equals to keep the comparison constant-time.
            return hash_equals(strtolower($stored), md5($password));
        }

        return password_verify($password, $stored);
    }

    /** True when a stored hash should be rewritten after a successful check. */
    public static function needsUpgrade(string $stored): bool
    {
        if (self::isLegacy($stored)) {
            return true;
        }
        return password_needs_rehash($stored, PASSWORD_BCRYPT, self::OPTIONS);
    }

    private static function isLegacy(string $stored): bool
    {
        return (bool) preg_match('/^[a-f0-9]{32}$/i', $stored);
    }
}
