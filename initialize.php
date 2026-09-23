<?php
/**
 * Application bootstrap: paths and configuration.
 *
 * Configuration comes from the environment, with a .env file for local work.
 * Nothing secret is committed - see .env.example and SECURITY.md.
 *
 * The version this replaces hardcoded the MySQL host, user, password and
 * database here, and carried a "developer" account with a fixed MD5 password
 * that bypassed the users table entirely. Both are gone.
 */

if (!defined('BASE_APP')) {
    define('BASE_APP', str_replace('\\', '/', __DIR__) . '/');
}

/**
 * Reads a .env file into the environment without overwriting anything the
 * server already set, so real environment variables always win.
 */
function load_env(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        // Strip one layer of matching quotes, so PASSWORD="a b" works.
        if (strlen($value) > 1 && $value[0] === $value[strlen($value) - 1] && in_array($value[0], ['"', "'"], true)) {
            $value = substr($value, 1, -1);
        }

        if ($key !== '' && getenv($key) === false && !isset($_ENV[$key]) && !isset($_SERVER[$key])) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

/** An environment value, or the default when it is unset or empty. */
function env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return $value;
}

load_env(BASE_APP . '.env');

if (!defined('BASE_URL')) {
    define('BASE_URL', rtrim(env('APP_BASE_URL', 'http://localhost:8000'), '/') . '/');
}

if (!defined('DB_SERVER')) {
    define('DB_SERVER', env('DB_HOST', '127.0.0.1'));
}
if (!defined('DB_PORT')) {
    define('DB_PORT', (int) env('DB_PORT', '3306'));
}
if (!defined('DB_USERNAME')) {
    define('DB_USERNAME', env('DB_USER', 'cbs'));
}
if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', env('DB_PASSWORD', ''));
}
if (!defined('DB_NAME')) {
    define('DB_NAME', env('DB_NAME', 'cbsphp'));
}

if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN));
}
if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', env('APP_TIMEZONE', 'Asia/Kolkata'));
}

// Kept for the handful of includes that still refer to the old names.
if (!defined('base_url')) {
    define('base_url', BASE_URL);
}
if (!defined('base_app')) {
    define('base_app', BASE_APP);
}
