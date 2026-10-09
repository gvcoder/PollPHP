<?php
/**
 * System Admin Dashboard & Platform Performance
 * PollPHP
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = get_db();
$user = current_user();

// Fetch Platform-Wide Metrics
// 1. Total Members
$user_stats = $pdo->query('
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role = "creator" THEN 1 ELSE 0 END) as total_creators,
        SUM(CASE WHEN status = "suspended" THEN 1 ELSE 0 END) as suspended_users
    FROM users
')->fetch();

// 2. Poll Statistics
$poll_stats = $pdo->query('
    SELECT 
        COUNT(*) as total_polls,
        SUM(CASE WHEN status = "active" AND expires_at > NOW() THEN 1 ELSE 0 END) as active_polls,
        SUM(CASE WHEN status = "suspended" THEN 1 ELSE 0 END) as suspended_polls,
        SUM(CASE WHEN status = "closed" OR (status = "active" AND expires_at <= NOW()) THEN 1 ELSE 0 END) as expired_polls,
        COALESCE(SUM(total_votes), 0) as total_platform_votes
    FROM polls
')->fetch();

// 3. Recent 5 Users
$recent_users = $pdo->query('
    SELECT id, name, email, role, status, created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 5
')->fetchAll();

// 4. Recent 5 Polls
$recent_polls = $pdo->query('
    SELECT p.id, p.slug, p.question, p.status, p.expires_at, p.total_votes, u.name as creator_name
    FROM polls p
    JOIN users u ON p.user_id = u.id
    ORDER BY p.created_at DESC
    LIMIT 5
')->fetchAll();

$page_title = 'System Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
            <span class="badge badge-admin">Administrator Mode</span>
        </div>
        <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--text-primary);">Platform Performance</h1>
        <p style="color: var(--text-secondary); font-size: 0.95rem;">Overall platform health, members, and poll moderation overview.</p>
    </div>

    <div style="display: flex; gap: 0.75rem;">
        <a href="/admin/users.php" class="btn btn-secondary btn-sm">👥 Manage Users</a>
        <a href="/admin/polls.php" class="btn btn-secondary btn-sm">📊 Manage All Polls</a>
    </div>
</div>

<!-- Platform KPI Cards -->
<div class="grid grid-4" style="margin-bottom: 2.5rem;">
    <div class="card" style="margin-bottom: 0; padding: 1.25rem;">
        <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Total Members</span>
        <div style="font-size: 2rem; font-weight: 800; color: var(--text-primary); margin-top: 0.35rem;">
            <?= (int)$user_stats['total_users'] ?>
        </div>
        <span style="font-size: 0.8rem; color: var(--text-secondary);">
            <?= (int)$user_stats['suspended_users'] ?> suspended
        </span>
    </div>

    <div class="card" style="margin-bottom: 0; padding: 1.25rem;">
        <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Active Polls</span>
        <div style="font-size: 2rem; font-weight: 800; color: var(--success); margin-top: 0.35rem;">
            <?= (int)$poll_stats['active_polls'] ?>
        </div>
        <span style="font-size: 0.8rem; color: var(--text-secondary);">
            of <?= (int)$poll_stats['total_polls'] ?> total polls
        </span>
    </div>

    <div class="card" style="margin-bottom: 0; padding: 1.25rem;">
        <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Total Votes Cast</span>
        <div style="font-size: 2rem; font-weight: 800; color: var(--primary); margin-top: 0.35rem;">
            <?= (int)$poll_stats['total_platform_votes'] ?>
        </div>
        <span style="font-size: 0.8rem; color: var(--text-secondary);">
            across all polls
        </span>
    </div>

    <div class="card" style="margin-bottom: 0; padding: 1.25rem;">
        <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Suspended Polls</span>
        <div style="font-size: 2rem; font-weight: 800; color: var(--danger); margin-top: 0.35rem;">
            <?= (int)$poll_stats['suspended_polls'] ?>
        </div>
        <span style="font-size: 0.8rem; color: var(--text-secondary);">
            moderated by admin
        </span>
    </div>
</div>

<!-- Recent Content Tables -->
<div class="grid grid-2">
    <!-- Recent Users -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2 class="card-title" style="font-size: 1.15rem;">Recent Members</h2>
            <a href="/admin/users.php" style="font-size: 0.85rem;">View All &rarr;</a>
        </div>

        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <tbody>
                <?php foreach ($recent_users as $u): ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 0.75rem 0;">
                            <strong><?= e($u['name']) ?></strong>
                            <div style="font-size: 0.8rem; color: var(--text-secondary);"><?= e($u['email']) ?></div>
                        </td>
                        <td style="padding: 0.75rem 0; text-align: right;">
                            <?php if ($u['status'] === 'suspended'): ?>
                                <span class="badge badge-suspended">Suspended</span>
                            <?php else: ?>
                                <span class="badge badge-active">Active</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Recent Polls -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2 class="card-title" style="font-size: 1.15rem;">Recent Polls</h2>
            <a href="/admin/polls.php" style="font-size: 0.85rem;">View All &rarr;</a>
        </div>

        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <tbody>
                <?php foreach ($recent_polls as $p): ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 0.75rem 0;">
                            <a href="/poll.php?id=<?= e($p['slug']) ?>" style="font-weight: 600; color: var(--text-primary);">
                                <?= e(mb_strimwidth($p['question'], 0, 45, '...')) ?>
                            </a>
                            <div style="font-size: 0.8rem; color: var(--text-secondary);">By <?= e($p['creator_name']) ?> &bull; <?= (int)$p['total_votes'] ?> votes</div>
                        </td>
                        <td style="padding: 0.75rem 0; text-align: right;">
                            <?php if ($p['status'] === 'suspended'): ?>
                                <span class="badge badge-suspended">Suspended</span>
                            <?php elseif ($p['status'] === 'active' && strtotime($p['expires_at']) > time()): ?>
                                <span class="badge badge-active">Active</span>
                            <?php else: ?>
                                <span class="badge badge-expired">Ended</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
