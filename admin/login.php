<?php
/**
 * System Admin Secure Login (NO Social Login Allowed)
 * PollPHP
 */
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in as admin
if (is_admin()) {
    redirect('/admin/index.php');
}

$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both your administrator email and password.';
        } else {
            $pdo = get_db();
            // Strict role = 'admin' check
            $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role, status FROM users WHERE email = ? AND role = "admin" LIMIT 1');
            $stmt->execute([$email]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'] ?? '')) {
                if ($admin['status'] === 'suspended') {
                    $error = 'This administrator account has been suspended.';
                } else {
                    login_user($admin);
                    set_flash('success', 'Welcome to the Admin Portal, ' . $admin['name'] . '.');
                    redirect('/admin/index.php');
                }
            } else {
                $error = 'Invalid administrator credentials or unauthorized account.';
            }
        }
    }
}

$page_title = 'System Administrator Login';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container container-sm">
    <div class="card" style="border-top: 4px solid var(--danger);">
        <div class="card-header" style="text-align: center;">
            <div style="font-size: 2rem; margin-bottom: 0.5rem;">🛡️</div>
            <h1 class="card-title">System Admin Portal</h1>
            <p class="card-subtitle">Secured credential-only access. Social login is prohibited.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="/admin/login.php">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="email">Admin Email Address</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>" required placeholder="admin@pollphp.local" autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required placeholder="••••••••••••">
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="background-color: #1e293b; border-color: #0f172a; margin-top: 1rem;">
                Authenticate Admin
            </button>
        </form>

        <div style="text-align: center; margin-top: 1.5rem; font-size: 0.85rem; color: var(--text-secondary);">
            <a href="/">&larr; Return to Public Home</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
