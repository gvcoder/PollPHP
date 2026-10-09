<?php
/**
 * Public Anonymous Poll Page
 * PollPHP
 */
require_once __DIR__ . '/includes/auth.php';

$pdo = get_db();
$slug = trim($_GET['id'] ?? '');

if (empty($slug)) {
    set_flash('danger', 'Poll not found.');
    redirect('/');
}

// Fetch Poll with Creator Details
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

if (!$poll) {
    http_response_code(404);
    $page_title = 'Poll Not Found';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="card" style="text-align: center; padding: 3rem 1rem;">
            <h2>404 - Poll Not Found</h2>
            <p style="color: var(--text-secondary); margin: 1rem 0;">The poll you are looking for does not exist or has been removed.</p>
            <a href="/" class="btn btn-primary">Go to Home</a>
          </div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Check admin suspension
if ($poll['status'] === 'suspended') {
    http_response_code(403);
    $page_title = 'Poll Suspended';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="card" style="text-align: center; padding: 3rem 1rem;">
            <h2>Poll Suspended</h2>
            <p style="color: var(--text-secondary); margin: 1rem 0;">This poll has been suspended by system administrators.</p>
            <a href="/" class="btn btn-primary">Go to Home</a>
          </div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Fetch Poll Options
$opt_stmt = $pdo->prepare('SELECT id, option_text, vote_count FROM poll_options WHERE poll_id = ? ORDER BY sort_order ASC, id ASC');
$opt_stmt->execute([$poll['id']]);
$options = $opt_stmt->fetchAll();

// Determine Voting State
$is_expired = ($poll['is_expired'] || $poll['status'] === 'closed');
$voter_identifier = get_voter_identifier((int)$poll['id']);

// Check if visitor has already voted
$has_voted = false;
if (isset($_COOKIE['voted_' . $poll['id']])) {
    $has_voted = true;
} else {
    $check_vote = $pdo->prepare('SELECT id FROM votes WHERE poll_id = ? AND voter_identifier = ? LIMIT 1');
    $check_vote->execute([$poll['id'], $voter_identifier]);
    if ($check_vote->fetch()) {
        $has_voted = true;
        setcookie('voted_' . $poll['id'], '1', time() + (86400 * 365), '/', '', false, true);
    }
}

// Handle Vote Submission
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_expired && !$has_voted) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = 'Security check failed. Please refresh the page.';
    } else {
        $chosen_option_id = (int)($_POST['option_id'] ?? 0);

        // Verify valid option belongs to this poll
        $valid_opt = false;
        foreach ($options as $opt) {
            if ((int)$opt['id'] === $chosen_option_id) {
                $valid_opt = true;
                break;
            }
        }

        if (!$valid_opt) {
            $error = 'Please select a valid option.';
        } else {
            $pdo->beginTransaction();
            try {
                // Record vote
                $client_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $insert_vote = $pdo->prepare('
                    INSERT INTO votes (poll_id, option_id, voter_identifier, ip_address)
                    VALUES (?, ?, ?, ?)
                ');
                $insert_vote->execute([$poll['id'], $chosen_option_id, $voter_identifier, $client_ip]);

                // Increment option count
                $inc_opt = $pdo->prepare('UPDATE poll_options SET vote_count = vote_count + 1 WHERE id = ?');
                $inc_opt->execute([$chosen_option_id]);

                // Increment total poll count
                $inc_poll = $pdo->prepare('UPDATE polls SET total_votes = total_votes + 1 WHERE id = ?');
                $inc_poll->execute([$poll['id']]);

                $pdo->commit();

                // Set persistent cookie
                setcookie('voted_' . $poll['id'], '1', time() + (86400 * 365), '/', '', false, true);
                $has_voted = true;
                set_flash('success', 'Thank you! Your vote has been recorded.');
                redirect('/poll.php?id=' . urlencode($poll['slug']));
            } catch (PDOException $e) {
                $pdo->rollBack();
                if ($e->getCode() == 23000) { // Duplicate entry
                    $has_voted = true;
                    $error = 'You have already voted in this poll.';
                } else {
                    $error = 'Failed to submit vote. Please try again.';
                }
            }
        }
    }
}

// Meta tags for WhatsApp / Social Media Rich Sharing
$og_title = $poll['question'];
$og_description = 'Vote now on ' . APP_NAME . ': ' . ($poll['description'] ?: 'Cast your vote in this poll.');
$og_url = APP_URL . '/poll.php?id=' . urlencode($poll['slug']);
$page_title = $poll['question'];

require_once __DIR__ . '/includes/header.php';
?>

<div class="container container-sm">
    <div class="card">
        <!-- Status & Expiration Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <div>
                <?php if ($is_expired): ?>
                    <span class="badge badge-expired">Poll Ended</span>
                <?php else: ?>
                    <span class="badge badge-active">Live Poll</span>
                <?php endif; ?>
            </div>
            <span style="font-size: 0.85rem; color: var(--text-secondary);">
                <?php if ($is_expired): ?>
                    Ended on <?= date('M d, Y', strtotime($poll['expires_at'])) ?>
                <?php else: ?>
                    Closes in <?= ceil((strtotime($poll['expires_at']) - time()) / 86400) ?> day(s)
                <?php endif; ?>
            </span>
        </div>

        <h1 style="font-size: 1.45rem; font-weight: 800; color: var(--text-primary); line-height: 1.35; margin-bottom: 0.75rem;">
            <?= e($poll['question']) ?>
        </h1>

        <?php if (!empty($poll['description'])): ?>
            <p style="color: var(--text-secondary); font-size: 0.95rem; margin-bottom: 1.5rem; line-height: 1.5;">
                <?= nl2br(e($poll['description'])) ?>
            </p>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Voting Interface -->
        <?php if ($has_voted): ?>
            <div style="background-color: #ecfdf5; border: 1px solid #a7f3d0; border-radius: var(--radius-md); padding: 1.5rem; text-align: center; margin: 1.5rem 0;">
                <div style="font-size: 2.2rem; margin-bottom: 0.5rem;">🎉</div>
                <h3 style="color: #065f46; font-size: 1.2rem; font-weight: 700;">You Have Voted!</h3>
                <p style="color: #047857; font-size: 0.95rem; margin-top: 0.35rem;">
                    Thank you for participating. Results will be published by the poll creator once the poll period finishes.
                </p>
            </div>

            <?php if ($poll['results_published']): ?>
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="/results.php?id=<?= e($poll['slug']) ?>" class="btn btn-primary btn-block">
                        View Official Published Results &rarr;
                    </a>
                </div>
            <?php endif; ?>

        <?php elseif ($is_expired): ?>
            <div style="background-color: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.5rem; text-align: center; margin: 1.5rem 0;">
                <div style="font-size: 2rem; margin-bottom: 0.5rem;">⏳</div>
                <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-primary);">This Poll Has Ended</h3>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.35rem;">
                    Voting is now closed.
                </p>
            </div>

            <?php if ($poll['results_published']): ?>
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="/results.php?id=<?= e($poll['slug']) ?>" class="btn btn-primary btn-block">
                        View Official Published Results &rarr;
                    </a>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Active Voting Form -->
            <form method="POST" action="/poll.php?id=<?= e($poll['slug']) ?>" style="margin-top: 1.5rem;">
                <?= csrf_field() ?>

                <div style="display: flex; flex-direction: column; gap: 0.85rem; margin-bottom: 1.5rem;">
                    <?php foreach ($options as $opt): ?>
                        <label style="display: flex; align-items: center; gap: 0.85rem; padding: 0.9rem 1.1rem; border: 2px solid var(--border-color); border-radius: var(--radius-md); cursor: pointer; transition: var(--transition); background: #ffffff;"
                               class="poll-option-label">
                            <input type="radio" name="option_id" value="<?= (int)$opt['id'] ?>" required style="accent-color: var(--primary); transform: scale(1.2);">
                            <span style="font-size: 1rem; font-weight: 600; color: var(--text-primary);">
                                <?= e($opt['option_text']) ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="padding: 0.8rem; font-size: 1rem;">
                    Submit Your Vote
                </button>
            </form>
        <?php endif; ?>

        <!-- Share Options & Creator Info Footer -->
        <div style="margin-top: 2rem; padding-top: 1.25rem; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div style="font-size: 0.85rem; color: var(--text-secondary);">
                Poll by <strong><?= e($poll['creator_name']) ?></strong>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary btn-sm" 
                        data-share 
                        data-share-title="<?= e($poll['question']) ?>" 
                        data-share-url="<?= $og_url ?>"
                        data-share-text="Vote in this poll: <?= e($poll['question']) ?>">
                    📲 Share on WhatsApp
                </button>
                <button type="button" class="btn btn-secondary btn-sm" data-copy-url="<?= $og_url ?>">
                    📋 Copy Link
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Highlight chosen radio card
document.querySelectorAll('.poll-option-label').forEach(label => {
    label.addEventListener('click', () => {
        document.querySelectorAll('.poll-option-label').forEach(l => {
            l.style.borderColor = 'var(--border-color)';
            l.style.backgroundColor = '#ffffff';
        });
        label.style.borderColor = 'var(--primary)';
        label.style.backgroundColor = 'var(--primary-light)';
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
