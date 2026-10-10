<?php
/**
 * CLI Admin Creation Utility
 * PollPHP
 *
 * Usage:
 *   php create-admin.php "Admin Name" "admin@yourdomain.com" "SecurePassword123!"
 * Or run interactively:
 *   php create-admin.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden: This script can only be run via CLI.\n";
    exit(1);
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

echo "=========================================\n";
echo "   PollPHP - Admin Account Setup\n";
echo "=========================================\n\n";

$name = $argv[1] ?? null;
$email = $argv[2] ?? null;
$password = $argv[3] ?? null;

if (!$name) {
    echo "Enter Admin Full Name: ";
    $name = trim(fgets(STDIN));
}

if (!$email) {
    echo "Enter Admin Email: ";
    $email = trim(fgets(STDIN));
}

if (!$password) {
    echo "Enter Admin Password: ";
    // Read password
    $password = trim(fgets(STDIN));
}

if (empty($name) || empty($email) || empty($password)) {
    echo "\n[ERROR] Name, email, and password are required.\n";
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "\n[ERROR] Invalid email format: {$email}\n";
    exit(1);
}

if (strlen($password) < 8) {
    echo "\n[ERROR] Password must be at least 8 characters.\n";
    exit(1);
}

$db = get_db();

// Check if user already exists
$stmt = $db->prepare("SELECT id, role FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$existing = $stmt->fetch();

$passwordHash = password_hash($password, PASSWORD_BCRYPT);

if ($existing) {
    // Promote/update existing user
    $updateStmt = $db->prepare("UPDATE users SET name = ?, password_hash = ?, role = 'admin', status = 'active' WHERE id = ?");
    $updateStmt->execute([$name, $passwordHash, $existing['id']]);
    echo "\n[SUCCESS] Existing user '{$email}' has been promoted to Administrator and password updated!\n";
} else {
    // Create new admin user
    $insertStmt = $db->prepare("INSERT INTO users (name, email, password_hash, auth_provider, role, status) VALUES (?, ?, ?, 'local', 'admin', 'active')");
    $insertStmt->execute([$name, $email, $passwordHash]);
    echo "\n[SUCCESS] Administrator account for '{$email}' created successfully!\n";
}

echo "You can now log in at: /admin/login.php\n\n";
