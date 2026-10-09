<?php
/**
 * Utility & Security Functions
 * PollPHP
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Escape output for HTML safely against XSS
 */
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a CSRF token for the session
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden CSRF token input tag
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Validate submitted CSRF token
 */
function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set a flash notification message
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type'    => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 */
function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Generate a random readable unique slug for polls (e.g. 6-8 chars)
 */
function generate_poll_slug(int $length = 8): string {
    return substr(bin2hex(random_bytes(ceil($length / 2))), 0, $length);
}

/**
 * Generate voter fingerprint hash for duplicate vote checking
 */
function get_voter_identifier(int $poll_id): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UnknownAgent';
    
    // Cookie salt unique to client browser if present
    if (empty($_COOKIE['voter_uid'])) {
        $cookie_uid = bin2hex(random_bytes(16));
        // 1 year cookie
        setcookie('voter_uid', $cookie_uid, time() + (86400 * 365), '/', '', false, true);
    } else {
        $cookie_uid = $_COOKIE['voter_uid'];
    }

    return hash('sha256', $poll_id . '|' . $ip . '|' . $agent . '|' . $cookie_uid . '|' . APP_SALT);
}

/**
 * Safe redirect helper
 */
function redirect(string $path): void {
    header('Location: ' . $path);
    exit;
}
