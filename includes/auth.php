<?php
/**
 * Authentication Helpers & Guards
 * PollPHP
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Check if any user is logged in
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Return current logged in user object or null
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }

    static $cached_user = null;
    if ($cached_user !== null) {
        return $cached_user;
    }

    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT id, name, email, role, status, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] === 'suspended') {
        // User was suspended or deleted
        logout_user();
        return null;
    }

    $cached_user = $user;
    return $cached_user;
}

/**
 * Check if the logged-in user is an administrator
 */
function is_admin(): bool {
    $user = current_user();
    return $user && $user['role'] === 'admin';
}

/**
 * Route Guard: Require logged in creator or admin
 */
function require_login(string $redirect_to = '/login.php'): void {
    if (!is_logged_in()) {
        set_flash('warning', 'Please sign in to access this page.');
        redirect($redirect_to);
    }
}

/**
 * Route Guard: Require administrator privilege
 */
function require_admin(string $redirect_to = '/admin/login.php'): void {
    if (!is_admin()) {
        set_flash('danger', 'Access denied. Administrator privileges required.');
        redirect($redirect_to);
    }
}

/**
 * Log in a user by populating session
 */
function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
}

/**
 * Log out user by clearing session
 */
function logout_user(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}
