<?php
/**
 * Dedicated Public Results Page (Publishable / Unpublishable by Creator)
 * PollPHP
 */
require_once __DIR__ . '/includes/auth.php';

$pdo = get_db();
$slug = trim($_GET['id'] ?? '');

if (empty($slug)) {
    redirect('/');
}

// Fetch Poll
$stmt = $pdo->prepare('
    SELECT p.*, u.name as creator_name,
           (p.expires_at <= NOW()) as is_expired
    FROM polls p
    JOIN users u ON p.user_id = u.id
    WHERE p.slug = ?
    LIMIT 1
');
$stmt->execute([$slug]);
$poll = $stmt->fetch();

// Check existence & moderation
if (!$poll || $poll['status'] === 'suspended') {
    http_response_code(404);
    $page_title = 'Page Not Found';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="card" style="text-align: center; padding: 3rem 1rem;">
            <h2>404 - Results Not Available</h2>
            <p style="color: var(--text-secondary); margin: 1rem 0;">The requested poll results are not available.</p>
            <a href="/" class="btn btn-primary">Go to Home</a>
          </div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Check if Creator has published these results
if (!$poll['results_published']) {
    http_response_code(403);
    $page_title = 'Results Not Published';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="card" style="text-align: center; padding: 3rem 1rem;">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🔒</div>
            <h2>Results Have Not Been Published</h2>
            <p style="color: var(--text-secondary); margin: 1rem 0;">The owner has not made the results of this poll public.</p>
            <a href="/" class="btn btn-primary">Go to Home</a>
          </div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Fetch Options & Results
$opt_stmt = $pdo->prepare('SELECT id, option_text, vote_count FROM poll_options WHERE poll_id = ? ORDER BY vote_count DESC, id ASC');
$opt_stmt->execute([$poll['id']]);
$options = $opt_stmt->fetchAll();

$total_votes = (int)$poll['total_votes'];

// Find winning count for highlighting
$top_vote_count = !empty($options) ? (int)$options[0]['vote_count'] : 0;

$page_title = 'Official Results: ' . $poll['question'];
$og_title = 'Results: ' . $poll['question'];
$og_description = 'Check out the final official results of this poll with ' . $total_votes . ' total votes.';
$og_url = APP_URL . '/results.php?id=' . urlencode($poll['slug']);

require_once __DIR__ . '/includes/header.php';
?>

<div class="container container-sm">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <span class="badge" style="background: #e0f2fe; color: #0369a1;">Official Results</span>
            <span style="font-size: 0.85rem; color: var(--text-secondary);">
                Total Votes: <strong><?= $total_votes ?></strong>
            </span>
        </div>

        <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-primary); line-height: 1.35; margin-bottom: 0.75rem;">
            <?= e($poll['question']) ?>
        </h1>

        <?php if (!empty($poll['description'])): ?>
            <p style="color: var(--text-secondary); font-size: 0.95rem; margin-bottom: 1.75rem;">
                <?= nl2br(e($poll['description'])) ?>
            </p>
        <?php endif; ?>

        <!-- Results Progress Visualizer -->
        <div style="display: flex; flex-direction: column; gap: 1.25rem; margin: 1.75rem 0;">
            <?php foreach ($options as $opt): ?>
                <?php 
                    $count = (int)$opt['vote_count'];
                    $pct = $total_votes > 0 ? round(($count / $total_votes) * 100, 1) : 0;
                    $is_winner = ($top_vote_count > 0 && $count === $top_vote_count);
                ?>
                <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1rem; position: relative; overflow: hidden;">
                    <!-- Visual Progress Bar Underlay -->
                    <div style="position: absolute; top: 0; left: 0; bottom: 0; width: <?= $pct ?>%; background: <?= $is_winner ? 'rgba(79, 70, 229, 0.12)' : 'rgba(226, 232, 240, 0.6)' ?>; transition: width 0.8s ease-in-out; z-index: 1;"></div>

                    <div style="position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: 600; font-size: 1rem; color: var(--text-primary);">
                            <?= e($opt['option_text']) ?>
                            <?php if ($is_winner && $total_votes > 0): ?>
                                <span style="font-size: 0.85rem; margin-left: 0.25rem;">🏆 Top Pick</span>
                            <?php endif; ?>
                        </span>
                        <div style="text-align: right;">
                            <span style="font-size: 1.15rem; font-weight: 800; color: <?= $is_winner ? 'var(--primary)' : 'var(--text-primary)' ?>;">
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

        <div style="margin-top: 2rem; padding-top: 1.25rem; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div style="font-size: 0.85rem; color: var(--text-secondary);">
                Conducted by <strong><?= e($poll['creator_name']) ?></strong>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary btn-sm" 
                        data-share 
                        data-share-title="<?= e($poll['question']) ?> - Results" 
                        data-share-url="<?= $og_url ?>"
                        data-share-text="Check out the poll results: <?= e($poll['question']) ?>">
                    📲 Share Results
                </button>
                <button type="button" class="btn btn-secondary btn-sm" data-copy-url="<?= $og_url ?>">
                    📋 Copy Link
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
