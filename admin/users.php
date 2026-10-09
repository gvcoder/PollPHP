<?php
/**
 * User Account Management (Admin)
 * PollPHP
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = get_db();
$admin = current_user();

// Handle Account Suspension / Reactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($token)) {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $action = $_POST['action'] ?? '';

        // Prevent admin from suspending themselves
        if ($user_id === (int)$admin['id']) {
            set_flash('danger', 'You cannot suspend your own administrator account.');
            redirect('/admin/users.php');
        }

        if ($action === 'toggle_status') {
            $stmt = $pdo->prepare('SELECT status, name FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$user_id]);
            $target = $stmt->fetch();

            if ($target) {
                $new_status = ($target['status'] === 'active') ? 'suspended' : 'active';
                $update = $pdo->prepare('UPDATE users SET status = ? WHERE id = ?');
                $update->execute([$new_status, $user_id]);

                set_flash('success', 'User ' . $target['name'] . ' status changed to ' . strtoupper($new_status) . '.');
                redirect('/admin/users.php');
            }
        }
    }
}

// Fetch all registered users
$stmt = $pdo->query('
    SELECT u.id, u.name, u.email, u.role, u.status, u.created_at,
           COUNT(p.id) as polls_count,
           COALESCE(SUM(p.total_votes), 0) as total_votes
    FROM users u
    LEFT JOIN polls p ON u.id = p.user_id
    GROUP BY u.id
    ORDER BY u.created_at DESC
');
$users = $stmt->fetchAll();

$page_title = 'Manage Registered Accounts';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <a href="/admin/index.php" class="btn btn-secondary btn-sm" style="margin-bottom: 0.5rem;">&larr; Admin Overview</a>
        <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem;">Registered Accounts</h1>
        <p style="color: var(--text-secondary); font-size: 0.95rem;">Manage platform users, creators, and administrators.</p>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2 class="card-title">User Accounts (<?= count($users) ?>)</h2>
    </div>

    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.95rem; text-align: left;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border-color); color: var(--text-secondary); font-size: 0.85rem; text-transform: uppercase;">
                    <th style="padding: 0.85rem 0.5rem;">Member</th>
                    <th style="padding: 0.85rem 0.5rem;">Role</th>
                    <th style="padding: 0.85rem 0.5rem;">Status</th>
                    <th style="padding: 0.85rem 0.5rem;">Polls</th>
                    <th style="padding: 0.85rem 0.5rem;">Total Votes</th>
                    <th style="padding: 0.85rem 0.5rem;">Joined</th>
                    <th style="padding: 0.85rem 0.5rem; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 0.85rem 0.5rem;">
                            <strong><?= e($u['name']) ?></strong>
                            <div style="font-size: 0.8rem; color: var(--text-secondary);"><?= e($u['email']) ?></div>
                        </td>
                        <td style="padding: 0.85rem 0.5rem;">
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="badge badge-admin">Admin</span>
                            <?php else: ?>
                                <span class="badge" style="background: #e2e8f0; color: #334155;">Creator</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.85rem 0.5rem;">
                            <?php if ($u['status'] === 'suspended'): ?>
                                <span class="badge badge-suspended">Suspended</span>
                            <?php else: ?>
                                <span class="badge badge-active">Active</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.85rem 0.5rem; font-weight: 600;">
                            <?= (int)$u['polls_count'] ?>
                        </td>
                        <td style="padding: 0.85rem 0.5rem; font-weight: 600;">
                            <?= (int)$u['total_votes'] ?>
                        </td>
                        <td style="padding: 0.85rem 0.5rem; font-size: 0.85rem; color: var(--text-secondary);">
                            <?= date('M d, Y', strtotime($u['created_at'])) ?>
                        </td>
                        <td style="padding: 0.85rem 0.5rem; text-align: right;">
                            <?php if ((int)$u['id'] !== (int)$admin['id']): ?>
                                <form method="POST" action="/admin/users.php" style="display: inline;" onsubmit="return confirm('Change status for <?= e(addslashes($u['name'])) ?>?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <?php if ($u['status'] === 'active'): ?>
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            Suspend
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            Resume / Activate
                                        </button>
                                    <?php endif; ?>
                                </form>
                            <?php else: ?>
                                <span style="font-size: 0.8rem; color: var(--text-secondary);">(Current User)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
