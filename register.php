<?php
/**
 * User Registration Page
 * PollPHP
 */
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('/dashboard.php');
}

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = 'Invalid request. Please refresh and try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($name)) {
            $errors[] = 'Please enter your name.';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please provide a valid email address.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters long.';
        }
        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            $pdo = get_db();
            // Check if email already registered
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'This email address is already registered. Please sign in instead.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $insert = $pdo->prepare('
                    INSERT INTO users (name, email, password_hash, auth_provider, role, status)
                    VALUES (?, ?, ?, "local", "creator", "active")
                ');
                $insert->execute([$name, $email, $hash]);
                $new_id = (int)$pdo->lastInsertId();

                // Auto-login newly registered creator
                login_user([
                    'id'    => $new_id,
                    'name'  => $name,
                    'email' => $email,
                    'role'  => 'creator'
                ]);

                set_flash('success', 'Welcome to ' . APP_NAME . '! Your account has been created.');
                redirect('/dashboard.php');
            }
        }
    }
}

$page_title = 'Create an Account';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container container-sm">
    <div class="card">
        <div class="card-header" style="text-align: center;">
            <h1 class="card-title">Join <?= e(APP_NAME) ?></h1>
            <p class="card-subtitle">Create and share instant polls with live analytics</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" style="display: block;">
                <ul style="margin-left: 1.25rem;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="/register.php">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="name">Your Name</label>
                <input type="text" id="name" name="name" class="form-control" value="<?= e($name) ?>" required placeholder="e.g. Alex Morgan" autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>" required placeholder="you@example.com">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required minlength="6" placeholder="At least 6 characters">
            </div>

            <div class="form-group">
                <label class="form-label" for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="6" placeholder="Repeat your password">
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">
                Create Free Account
            </button>
        </form>

        <div style="text-align: center; margin-top: 1.5rem; font-size: 0.9rem; color: var(--text-secondary);">
            Already have an account? <a href="/login.php">Sign In</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
