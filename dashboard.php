<?php
/**
 * Poll Creator Dashboard & Account-Level Analysis
 * PollPHP
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$pdo = get_db();

// Handle Publish / Unpublish Results Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($token)) {
        $poll_id = (int)($_POST['poll_id'] ?? 0);
        $action = $_POST['action'];

        if ($action === 'toggle_publish') {
            $stmt = $pdo->prepare('SELECT id, results_published, expires_at FROM polls WHERE id = ? AND user_id = ? LIMIT 1');
            $stmt->execute([$poll_id, $user['id']]);
            $poll = $stmt->fetch();

            if ($poll) {
                $new_state = $poll['results_published'] ? 0 : 1;
                $update = $pdo->prepare('UPDATE polls SET results_published = ? WHERE id = ?');
                $update->execute([$new_state, $poll_id]);

                set_flash('success', $new_state ? 'Poll results page is now publicly visible!' : 'Poll results page has been unpublished.');
                redirect('/dashboard.php');
            }
        } elseif ($action === 'close_poll') {
            $stmt = $pdo->prepare('UPDATE polls SET status = "closed" WHERE id = ? AND user_id = ?');
            $stmt->execute([$poll_id, $user['id']]);
            set_flash('info', 'Poll has been closed.');
            redirect('/dashboard.php');
        } elseif ($action === 'delete_poll') {
            $stmt = $pdo->prepare('DELETE FROM polls WHERE id = ? AND user_id = ?');
            $stmt->execute([$poll_id, $user['id']]);
            set_flash('success', 'Poll has been deleted.');
            redirect('/dashboard.php');
        }
    }
}

// Fetch Account-Level Metrics
$stmt = $pdo->prepare('
    SELECT 
        COUNT(*) as total_polls,
        SUM(CASE WHEN status = "active" AND expires_at > NOW() THEN 1 ELSE 0 END) as active_polls,
        SUM(CASE WHEN status = "closed" OR expires_at <= NOW() THEN 1 ELSE 0 END) as expired_polls,
        COALESCE(SUM(total_votes), 0) as total_votes
    FROM polls
    WHERE user_id = ?
');
$stmt->execute([$user['id']]);
$metrics = $stmt->fetch();

$total_polls = (int)$metrics['total_polls'];
$active_polls_count = (int)$metrics['active_polls'];
$expired_polls_count = (int)$metrics['expired_polls'];
$total_votes = (int)$metrics['total_votes'];
$avg_engagement = $total_polls > 0 ? round($total_votes / $total_polls, 1) : 0;

// Fetch All Creator's Polls
$stmt = $pdo->prepare('
    SELECT id, slug, question, duration_days, expires_at, status, results_published, total_votes, created_at,
           (expires_at <= NOW()) as is_expired
    FROM polls
    WHERE user_id = ?
    ORDER BY created_at DESC
');
$stmt->execute([$user['id']]);
$polls = $stmt->fetchAll();

$page_title = 'Creator Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.85rem; font-weight: 700; color: var(--text-primary);">Hello, <?= e($user['name']) ?></h1>
        <p style="color: var(--text-secondary); font-size: 0.95rem;">Here is your account overview and poll analytics.</p>
    </div>
    <a href="/create-poll.php" class="btn btn-primary">+ Create New Poll</a>
</div>

<!-- Account-Level Analytics Cards -->
<div class="grid grid-4" style="margin-bottom: 2.5rem;">
    <div class="card" style="margin-bottom: 0; padding: 1.25rem;">
        <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Total Polls</span>
        <div style="font-size: 2rem; font-weight: 800; color: var(--text-primary); margin-top: 0.35rem;"><?= $total_polls ?></div>
    </div>
    <div class="card" style="margin-bottom: 0; padding: 1.25rem;">
        <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Active Polls</span>
        <div style="font-size: 2rem; font-weight: 800; color: var(--success); margin-top: 0.35rem;"><?= $active_polls_count ?></div>
    </div>
    <div class="card" style="margin-bottom: 0; padding: 1.25rem;">
        <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Total Votes Received</span>
        <div style="font-size: 2rem; font-weight: 800; color: var(--primary); margin-top: 0.35rem;"><?= $total_votes ?></div>
    </div>
    <div class="card" style="margin-bottom: 0; padding: 1.25rem;">
        <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Avg Engagement / Poll</span>
        <div style="font-size: 2rem; font-weight: 800; color: var(--secondary); margin-top: 0.35rem;"><?= $avg_engagement ?> <span style="font-size: 0.9rem; font-weight: 500; color: var(--text-secondary);">votes</span></div>
    </div>
</div>

<!-- Poll List -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2 class="card-title">Your Polls</h2>
        <span style="font-size: 0.9rem; color: var(--text-secondary);"><?= count($polls) ?> total</span>
    </div>

    <?php if (empty($polls)): ?>
        <div style="text-align: center; padding: 2rem 1rem; color: var(--text-secondary);">
            <p style="margin-bottom: 1rem;">You haven't created any polls yet.</p>
            <a href="/create-poll.php" class="btn btn-primary btn-sm">+ Create Your First Poll</a>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <?php foreach ($polls as $p): ?>
                <?php 
                    $is_active = ($p['status'] === 'active' && !$p['is_expired']);
                    $poll_url = APP_URL . '/poll.php?id=' . e($p['slug']);
                    $results_url = APP_URL . '/results.php?id=' . e($p['slug']);
                ?>
                <div style="border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem; background: #ffffff;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 260px;">
                            <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.4rem;">
                                <?php if ($p['status'] === 'suspended'): ?>
                                    <span class="badge badge-suspended">Suspended by Admin</span>
                                <?php elseif ($is_active): ?>
                                    <span class="badge badge-active">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-expired">Ended / Closed</span>
                                <?php endif; ?>

                                <?php if ($p['results_published']): ?>
                                    <span class="badge" style="background: #e0f2fe; color: #0369a1;">Public Results Live</span>
                                <?php endif; ?>

                                <span style="font-size: 0.8rem; color: var(--text-secondary);">
                                    Expires: <?= date('M d, Y H:i', strtotime($p['expires_at'])) ?>
                                </span>
                            </div>

                            <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.4rem;">
                                <a href="/poll.php?id=<?= e($p['slug']) ?>"><?= e($p['question']) ?></a>
                            </h3>

                            <div style="display: flex; gap: 1.5rem; font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.5rem;">
                                <span>🗳️ <strong><?= (int)$p['total_votes'] ?></strong> votes</span>
                                <span>⏱️ <strong><?= (int)$p['duration_days'] ?></strong> days duration</span>
                                <span>📅 Created: <?= date('M d, Y', strtotime($p['created_at'])) ?></span>
                            </div>
                        </div>

                        <!-- Actions & Controls -->
                        <div style="display: flex; flex-direction: column; gap: 0.5rem; align-items: flex-end;">
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                <!-- Owner internal analysis link -->
                                <a href="/poll-analysis.php?id=<?= e($p['slug']) ?>" class="btn btn-secondary btn-sm">
                                    📊 Detailed Analysis
                                </a>

                                <!-- Share button -->
                                <button type="button" class="btn btn-secondary btn-sm" 
                                        data-share 
                                        data-share-title="<?= e($p['question']) ?>" 
                                        data-share-url="<?= $poll_url ?>"
                                        data-share-text="Vote now: <?= e($p['question']) ?>">
                                    📲 Share
                                </button>

                                <!-- Copy Poll Link -->
                                <button type="button" class="btn btn-secondary btn-sm" data-copy-url="<?= $poll_url ?>">
                                    📋 Copy Link
                                </button>
                            </div>

                            <!-- Publish/Unpublish Results when poll has ended -->
                            <div style="display: flex; gap: 0.5rem; align-items: center; margin-top: 0.25rem;">
                                <?php if (!$is_active && $p['status'] !== 'suspended'): ?>
                                    <form method="POST" action="/dashboard.php" style="display: inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_publish">
                                        <input type="hidden" name="poll_id" value="<?= (int)$p['id'] ?>">
                                        <?php if ($p['results_published']): ?>
                                            <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--danger); border-color: #fecaca;">
                                                🚫 Unpublish Results
                                            </button>
                                            <a href="/results.php?id=<?= e($p['slug']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="color: var(--primary);">
                                                🔗 View Public Results
                                            </a>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                🌐 Publish Results Page
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>

                                <!-- Delete Form -->
                                <form method="POST" action="/dashboard.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this poll and all its votes?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_poll">
                                    <input type="hidden" name="poll_id" value="<?= (int)$p['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
