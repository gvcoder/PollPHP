<?php
/**
 * Home Page / Landing & Public Poll Explorer
 * PollPHP
 */
require_once __DIR__ . '/includes/auth.php';

$pdo = get_db();
$user = current_user();

// Fetch recently active public polls
$stmt = $pdo->prepare('
    SELECT p.id, p.slug, p.question, p.description, p.expires_at, p.total_votes, u.name as creator_name
    FROM polls p
    JOIN users u ON p.user_id = u.id
    WHERE p.status = "active" AND p.expires_at > NOW()
    ORDER BY p.created_at DESC
    LIMIT 6
');
$stmt->execute();
$active_polls = $stmt->fetchAll();

$page_title = 'Create & Participate in Instant Polls';
require_once __DIR__ . '/includes/header.php';
?>

<div style="text-align: center; padding: 2.5rem 0 3rem;">
    <h1 style="font-size: 2.5rem; font-weight: 800; letter-spacing: -0.025em; margin-bottom: 1rem; color: var(--text-primary);">
        Simple, Fast & Frictionless <span style="color: var(--primary);">Polling</span>
    </h1>
    <p style="font-size: 1.15rem; color: var(--text-secondary); max-width: 620px; margin: 0 auto 2rem;">
        Create polls in seconds. Share them anywhere via WhatsApp and social channels. Anyone can participate anonymously without signing up.
    </p>
    <div>
        <?php if ($user): ?>
            <a href="/create-poll.php" class="btn btn-primary" style="font-size: 1.05rem; padding: 0.8rem 1.8rem;">
                + Create a Poll Now
            </a>
            <a href="/dashboard.php" class="btn btn-secondary" style="font-size: 1.05rem; padding: 0.8rem 1.8rem; margin-left: 0.75rem;">
                My Dashboard
            </a>
        <?php else: ?>
            <a href="/register.php" class="btn btn-primary" style="font-size: 1.05rem; padding: 0.8rem 1.8rem;">
                Start Polling for Free
            </a>
            <a href="/login.php" class="btn btn-secondary" style="font-size: 1.05rem; padding: 0.8rem 1.8rem; margin-left: 0.75rem;">
                Sign In
            </a>
        <?php endif; ?>
    </div>
</div>

<div style="margin-top: 1rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.4rem; font-weight: 700;">Active Public Polls</h2>
    </div>

    <?php if (empty($active_polls)): ?>
        <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
            <p style="color: var(--text-secondary); font-size: 1.1rem; margin-bottom: 1.25rem;">
                No active public polls right now. Be the first to create one!
            </p>
            <a href="/create-poll.php" class="btn btn-primary">Create the First Poll</a>
        </div>
    <?php else: ?>
        <div class="grid grid-3">
            <?php foreach ($active_polls as $poll): ?>
                <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                            <span class="badge badge-active">Live Poll</span>
                            <span style="font-size: 0.8rem; color: var(--text-secondary);">
                                <?= (int)$poll['total_votes'] ?> vote<?= $poll['total_votes'] == 1 ? '' : 's' ?>
                            </span>
                        </div>
                        <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 0.5rem; line-height: 1.4;">
                            <a href="/poll.php?id=<?= e($poll['slug']) ?>" style="color: inherit;">
                                <?= e($poll['question']) ?>
                            </a>
                        </h3>
                        <?php if (!empty($poll['description'])): ?>
                            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1rem;">
                                <?= e(mb_strimwidth($poll['description'], 0, 90, '...')) ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.8rem; color: var(--text-secondary);">
                            By <?= e($poll['creator_name']) ?>
                        </span>
                        <a href="/poll.php?id=<?= e($poll['slug']) ?>" class="btn btn-primary btn-sm">
                            Vote Now &rarr;
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
