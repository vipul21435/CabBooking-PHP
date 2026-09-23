<?php
/**
 * Shared bootstrap: configuration, session, database handle and view helpers.
 */

require_once __DIR__ . '/initialize.php';

date_default_timezone_set(APP_TIMEZONE);

// Errors belong in the log in production; showing them leaks paths and queries.
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        // Secure cookies only once the site is actually served over HTTPS.
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

require_once __DIR__ . '/classes/DBConnection.php';
require_once __DIR__ . '/classes/Password.php';
require_once __DIR__ . '/classes/SystemSettings.php';

$db = new DBConnection();
$conn = $db->conn;

/**
 * Escapes text for HTML output. Every value that reaches a page should go
 * through it.
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Client-side redirect, kept because the views rely on it. */
function redirect(string $url = ''): void
{
    if ($url !== '') {
        echo '<script>location.href="' . e(BASE_URL . $url) . '"</script>';
    }
}

/** A usable URL for an uploaded image, or the placeholder. */
function validate_image($file): string
{
    $placeholder = BASE_URL . 'dist/img/no-image-available.png';
    if (empty($file)) {
        return $placeholder;
    }

    $path = explode('?', (string) $file)[0];

    // Never let a stored value climb out of the application directory.
    if ($path === '' || str_contains($path, '..')) {
        return $placeholder;
    }

    return is_file(BASE_APP . $path) ? BASE_URL . $file : $placeholder;
}

function isMobileDevice(): bool
{
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return (bool) preg_match('/iphone|ipod|ipad|android|blackberry|webos/i', $agent);
}

/**
 * Normalises the `page` parameter the three front controllers route on.
 *
 * A page name may only be letters, digits, underscores, hyphens, and single
 * forward slashes between segments. Anything else falls back to the default,
 * which keeps a relative path out of the include.
 */
function safe_page_name($value, string $default = 'home'): string
{
    $page = trim((string) $value);

    if ($page === '' || !preg_match('#^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*$#', $page)) {
        return $default;
    }

    return $page;
}
