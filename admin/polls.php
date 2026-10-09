<?php
/**
 * Poll Management & Moderation (Admin)
 * PollPHP
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = get_db();

// Handle Poll Moderation Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($token)) {
        $poll_id = (int)($_POST['poll_id'] ?? 0);
        $action = $_POST['action'] ?? '';

        if ($action === 'toggle_suspend') {
            $stmt = $pdo->prepare('SELECT status FROM polls WHERE id = ? LIMIT 1');
            $stmt->execute([$poll_id]);
            $poll = $stmt->fetch();

            if ($poll) {
                $new_status = ($poll['status'] === 'suspended') ? 'active' : 'suspended';
                $update = $pdo->prepare('UPDATE polls SET status = ? WHERE id = ?');
                $update->execute([$new_status, $poll_id]);

                set_flash('success', 'Poll status updated to ' . strtoupper($new_status) . '.');
                redirect('/admin/polls.php');
            }
        } elseif ($action === 'delete_poll') {
            $stmt = $pdo->prepare('DELETE FROM polls WHERE id = ?');
            $stmt->execute([$poll_id]);
            set_flash('success', 'Poll has been deleted permanently.');
            redirect('/admin/polls.php');
        }
    }
}

// Filter parameter (all, active, inactive, suspended)
$filter = $_GET['filter'] ?? 'all';
$query = '
    SELECT p.*, u.name as creator_name, u.email as creator_email,
           (p.expires_at <= NOW()) as is_expired
    FROM polls p
    JOIN users u ON p.user_id = u.id
';
$params = [];

if ($filter === 'active') {
    $query .= ' WHERE p.status = "active" AND p.expires_at > NOW()';
} elseif ($filter === 'inactive') {
    $query .= ' WHERE (p.status = "closed" OR (p.status = "active" AND p.expires_at <= NOW()))';
} elseif ($filter === 'suspended') {
    $query .= ' WHERE p.status = "suspended"';
}

$query .= ' ORDER BY p.created_at DESC';

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$polls = $stmt->fetchAll();

$page_title = 'Manage All Polls';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <a href="/admin/index.php" class="btn btn-secondary btn-sm" style="margin-bottom: 0.5rem;">&larr; Admin Overview</a>
        <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem;">Platform Polls</h1>
        <p style="color: var(--text-secondary); font-size: 0.95rem;">Monitor, moderate, or suspend public polls.</p>
    </div>

    <!-- Filter Buttons -->
    <div style="display: flex; gap: 0.5rem; background: #e2e8f0; padding: 0.25rem; border-radius: var(--radius-md);">
        <a href="/admin/polls.php?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-secondary' ?>">All</a>
        <a href="/admin/polls.php?filter=active" class="btn btn-sm <?= $filter === 'active' ? 'btn-primary' : 'btn-secondary' ?>">Active</a>
        <a href="/admin/polls.php?filter=inactive" class="btn btn-sm <?= $filter === 'inactive' ? 'btn-primary' : 'btn-secondary' ?>">Inactive / Ended</a>
        <a href="/admin/polls.php?filter=suspended" class="btn btn-sm <?= $filter === 'suspended' ? 'btn-primary' : 'btn-secondary' ?>">Suspended</a>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2 class="card-title">Polls (<?= count($polls) ?>)</h2>
    </div>

    <?php if (empty($polls)): ?>
        <p style="color: var(--text-secondary); text-align: center; padding: 2rem 0;">No polls matching the selected filter.</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.95rem; text-align: left;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color); color: var(--text-secondary); font-size: 0.85rem; text-transform: uppercase;">
                        <th style="padding: 0.85rem 0.5rem;">Poll Question</th>
                        <th style="padding: 0.85rem 0.5rem;">Creator</th>
                        <th style="padding: 0.85rem 0.5rem;">Status</th>
                        <th style="padding: 0.85rem 0.5rem;">Votes</th>
                        <th style="padding: 0.85rem 0.5rem;">Expires</th>
                        <th style="padding: 0.85rem 0.5rem; text-align: right;">Moderation Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($polls as $p): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 0.85rem 0.5rem; max-width: 320px;">
                                <a href="/poll.php?id=<?= e($p['slug']) ?>" target="_blank" style="font-weight: 600; color: var(--text-primary);">
                                    <?= e($p['question']) ?>
                                </a>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);">
                                    Slug: <?= e($p['slug']) ?> &bull; 
                                    <a href="/poll-analysis.php?id=<?= e($p['slug']) ?>">View Analysis</a>
                                </div>
                            </td>
                            <td style="padding: 0.85rem 0.5rem;">
                                <strong><?= e($p['creator_name']) ?></strong>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);"><?= e($p['creator_email']) ?></div>
                            </td>
                            <td style="padding: 0.85rem 0.5rem;">
                                <?php if ($p['status'] === 'suspended'): ?>
                                    <span class="badge badge-suspended">Suspended</span>
                                <?php elseif ($p['status'] === 'active' && !$p['is_expired']): ?>
                                    <span class="badge badge-active">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-expired">Ended / Closed</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.85rem 0.5rem; font-weight: 700; color: var(--primary);">
                                <?= (int)$p['total_votes'] ?>
                            </td>
                            <td style="padding: 0.85rem 0.5rem; font-size: 0.85rem; color: var(--text-secondary);">
                                <?= date('M d, Y H:i', strtotime($p['expires_at'])) ?>
                            </td>
                            <td style="padding: 0.85rem 0.5rem; text-align: right;">
                                <div style="display: inline-flex; gap: 0.4rem;">
                                    <form method="POST" action="/admin/polls.php" style="display: inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_suspend">
                                        <input type="hidden" name="poll_id" value="<?= (int)$p['id'] ?>">
                                        <?php if ($p['status'] === 'suspended'): ?>
                                            <button type="submit" class="btn btn-primary btn-sm">Resume</button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--danger); border-color: #fecaca;">Suspend</button>
                                        <?php endif; ?>
                                    </form>

                                    <form method="POST" action="/admin/polls.php" style="display: inline;" onsubmit="return confirm('Permanently delete this poll and all votes?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_poll">
                                        <input type="hidden" name="poll_id" value="<?= (int)$p['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
