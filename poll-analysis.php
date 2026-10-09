<?php
/**
 * Detailed Poll Analysis for Creators
 * PollPHP
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$pdo = get_db();
$slug = trim($_GET['id'] ?? '');

if (empty($slug)) {
    redirect('/dashboard.php');
}

// Fetch Poll ensuring current user is owner (or admin)
$stmt = $pdo->prepare('
    SELECT p.*, (p.expires_at <= NOW()) as is_expired
    FROM polls p
    WHERE p.slug = ? AND (p.user_id = ? OR ? = "admin")
    LIMIT 1
');
$stmt->execute([$slug, $user['id'], $user['role']]);
$poll = $stmt->fetch();

if (!$poll) {
    set_flash('danger', 'Poll not found or access denied.');
    redirect('/dashboard.php');
}

// Fetch Options & Counts
$opt_stmt = $pdo->prepare('SELECT id, option_text, vote_count FROM poll_options WHERE poll_id = ? ORDER BY vote_count DESC, id ASC');
$opt_stmt->execute([$poll['id']]);
$options = $opt_stmt->fetchAll();

$total_votes = (int)$poll['total_votes'];

// Daily Vote Velocity (Last 7 Days)
$history_stmt = $pdo->prepare('
    SELECT DATE(created_at) as vote_date, COUNT(*) as daily_count
    FROM votes
    WHERE poll_id = ?
    GROUP BY DATE(created_at)
    ORDER BY vote_date ASC
');
$history_stmt->execute([$poll['id']]);
$daily_history = $history_stmt->fetchAll();

$page_title = 'Analysis: ' . $poll['question'];
require_once __DIR__ . '/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <a href="/dashboard.php" class="btn btn-secondary btn-sm" style="margin-bottom: 0.5rem;">&larr; Back to Dashboard</a>
        <h1 style="font-size: 1.75rem; font-weight: 700; color: var(--text-primary); margin-top: 0.25rem;">
            <?= e($poll['question']) ?>
        </h1>
        <p style="color: var(--text-secondary); font-size: 0.9rem;">
            Poll Analysis & Performance Metrics
        </p>
    </div>

    <div style="display: flex; gap: 0.5rem;">
        <?php if ($poll['results_published']): ?>
            <a href="/results.php?id=<?= e($poll['slug']) ?>" target="_blank" class="btn btn-primary btn-sm">
                View Public Results Page &rarr;
            </a>
        <?php endif; ?>
        <a href="/poll.php?id=<?= e($poll['slug']) ?>" target="_blank" class="btn btn-secondary btn-sm">
            View Voting Page &rarr;
        </a>
    </div>
</div>

<!-- Overview Metric Cards -->
<div class="grid grid-3" style="margin-bottom: 2rem;">
    <div class="card" style="margin-bottom: 0; padding: 1.25rem;">
        <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Total Votes</span>
        <div style="font-size: 2rem; font-weight: 800; color: var(--primary); margin-top: 0.35rem;"><?= $total_votes ?></div>
    </div>
    <div class="card" style="margin-bottom: 0; padding: 1.25rem;">
        <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Poll Status</span>
        <div style="font-size: 1.2rem; font-weight: 700; margin-top: 0.6rem;">
            <?php if ($poll['status'] === 'suspended'): ?>
                <span class="badge badge-suspended">Suspended</span>
            <?php elseif ($poll['is_expired'] || $poll['status'] === 'closed'): ?>
                <span class="badge badge-expired">Ended</span>
            <?php else: ?>
                <span class="badge badge-active">Live & Active</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card" style="margin-bottom: 0; padding: 1.25rem;">
        <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Public Results URL</span>
        <div style="font-size: 1.2rem; font-weight: 700; margin-top: 0.6rem;">
            <?php if ($poll['results_published']): ?>
                <span style="color: var(--success); font-size: 0.95rem;">● Published Online</span>
            <?php else: ?>
                <span style="color: var(--text-secondary); font-size: 0.95rem;">○ Hidden (Unpublished)</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Option Breakdown -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h2 class="card-title">Option Distribution</h2>
    </div>

    <div style="display: flex; flex-direction: column; gap: 1rem;">
        <?php foreach ($options as $opt): ?>
            <?php 
                $count = (int)$opt['vote_count'];
                $pct = $total_votes > 0 ? round(($count / $total_votes) * 100, 1) : 0;
            ?>
            <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1rem; position: relative; overflow: hidden;">
                <div style="position: absolute; top: 0; left: 0; bottom: 0; width: <?= $pct ?>%; background: rgba(79, 70, 229, 0.15); transition: width 0.6s ease; z-index: 1;"></div>
                <div style="position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 600; color: var(--text-primary); font-size: 1rem;">
                        <?= e($opt['option_text']) ?>
                    </span>
                    <div style="text-align: right;">
                        <span style="font-size: 1.1rem; font-weight: 800; color: var(--primary);">
                            <?= $pct ?>%
                        </span>
                        <span style="font-size: 0.8rem; color: var(--text-secondary); display: block;">
                            <?= $count ?> vote<?= $count === 1 ? '' : 's' ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Daily Votes Timeline -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Vote Timeline</h2>
        <p class="card-subtitle">Daily voting activity</p>
    </div>

    <?php if (empty($daily_history)): ?>
        <p style="color: var(--text-secondary); text-align: center; padding: 1.5rem 0;">No votes recorded yet.</p>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.95rem;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border-color); color: var(--text-secondary);">
                    <th style="padding: 0.75rem 0;">Date</th>
                    <th style="padding: 0.75rem 0; text-align: right;">Votes Cast</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($daily_history as $row): ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 0.75rem 0; font-weight: 500;">
                            <?= date('l, M d, Y', strtotime($row['vote_date'])) ?>
                        </td>
                        <td style="padding: 0.75rem 0; text-align: right; font-weight: 700; color: var(--primary);">
                            <?= (int)$row['daily_count'] ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
