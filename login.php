<?php
/**
 * User & Creator Login Page
 * PollPHP
 */
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('/dashboard.php');
}

$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both your email and password.';
        } else {
            $pdo = get_db();
            $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role, status FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'] ?? '')) {
                if ($user['status'] === 'suspended') {
                    $error = 'Your account has been suspended. Please contact support.';
                } else {
                    login_user($user);
                    set_flash('success', 'Welcome back, ' . $user['name'] . '!');
                    redirect('/dashboard.php');
                }
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}

$page_title = 'Sign In';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container container-sm">
    <div class="card">
        <div class="card-header" style="text-align: center;">
            <h1 class="card-title">Welcome Back</h1>
            <p class="card-subtitle">Sign in to manage your polls and view results</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="/login.php">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>" required placeholder="you@example.com" autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required placeholder="Enter your password">
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">
                Sign In
            </button>
        </form>

        <div style="text-align: center; margin-top: 1.5rem; font-size: 0.9rem; color: var(--text-secondary);">
            Don't have an account yet? <a href="/register.php">Create Account</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
