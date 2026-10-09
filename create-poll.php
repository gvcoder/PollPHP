<?php
/**
 * Create New Poll Page
 * PollPHP
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$errors = [];
$question = '';
$description = '';
$duration_days = 3;
$options = ['', '']; // Default 2 empty options

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = 'Invalid request security token. Please try again.';
    } else {
        $question = trim($_POST['question'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $duration_days = (int)($_POST['duration_days'] ?? 3);
        $raw_options = $_POST['options'] ?? [];

        // Validate Question
        if (empty($question)) {
            $errors[] = 'Please enter a poll question.';
        } elseif (mb_strlen($question) > 500) {
            $errors[] = 'Poll question cannot exceed 500 characters.';
        }

        // Validate Duration
        if (!in_array($duration_days, POLL_DURATIONS, true)) {
            $errors[] = 'Please select a valid duration (3, 5, or 7 days).';
        }

        // Clean & Validate Options
        $clean_options = [];
        if (is_array($raw_options)) {
            foreach ($raw_options as $opt) {
                $val = trim($opt);
                if ($val !== '') {
                    $clean_options[] = $val;
                }
            }
        }

        if (count($clean_options) < 2) {
            $errors[] = 'A poll requires at least 2 distinct options.';
        } elseif (count($clean_options) > 10) {
            $errors[] = 'A poll can have at most 10 options.';
        }

        if (empty($errors)) {
            $pdo = get_db();
            $pdo->beginTransaction();

            try {
                $slug = generate_poll_slug(8);
                // Ensure slug uniqueness
                $check = $pdo->prepare('SELECT id FROM polls WHERE slug = ? LIMIT 1');
                $check->execute([$slug]);
                while ($check->fetch()) {
                    $slug = generate_poll_slug(8);
                    $check->execute([$slug]);
                }

                // Insert Poll
                $poll_stmt = $pdo->prepare('
                    INSERT INTO polls (user_id, slug, question, description, duration_days, expires_at, status, results_published, total_votes)
                    VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), "active", 0, 0)
                ');
                $poll_stmt->execute([
                    $user['id'],
                    $slug,
                    $question,
                    $description ?: null,
                    $duration_days,
                    $duration_days
                ]);
                $poll_id = (int)$pdo->lastInsertId();

                // Insert Options
                $opt_stmt = $pdo->prepare('
                    INSERT INTO poll_options (poll_id, option_text, vote_count, sort_order)
                    VALUES (?, ?, 0, ?)
                ');
                foreach ($clean_options as $order => $opt_text) {
                    $opt_stmt->execute([$poll_id, $opt_text, $order]);
                }

                $pdo->commit();
                set_flash('success', 'Your poll has been created successfully!');
                redirect('/dashboard.php');
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Failed to create poll: ' . (APP_DEBUG ? $e->getMessage() : 'Internal error');
            }
        }
    }
}

$page_title = 'Create a Simple Poll';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container container-sm">
    <div class="card">
        <div class="card-header">
            <h1 class="card-title">Create a Simple Poll</h1>
            <p class="card-subtitle">Set your question, options, and how long the poll remains open.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" style="display: block;">
                <ul style="margin-left: 1.25rem;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="/create-poll.php" id="pollForm">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="question">Poll Question *</label>
                <input type="text" id="question" name="question" class="form-control" value="<?= e($question) ?>" required placeholder="e.g. Which programming language do you use most?" autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Additional Context / Description (Optional)</label>
                <textarea id="description" name="description" class="form-control" rows="2" placeholder="Optional details for participants..."><?= e($description) ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Poll Duration *</label>
                <div style="display: flex; gap: 1rem;">
                    <?php foreach (POLL_DURATIONS as $days): ?>
                        <label style="display: flex; align-items: center; gap: 0.4rem; cursor: pointer; padding: 0.5rem 0.9rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: #ffffff;">
                            <input type="radio" name="duration_days" value="<?= $days ?>" <?= $duration_days === $days ? 'checked' : '' ?>>
                            <span style="font-weight: 600;"><?= $days ?> Days</span>
                            <?php if ($days === 7): ?>
                                <span style="font-size: 0.8rem; color: var(--text-secondary);">(1 week)</span>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Poll Options (Min: 2, Max: 10) *</label>
                <div id="optionsContainer" style="display: flex; flex-direction: column; gap: 0.65rem;">
                    <?php 
                    $current_opts = !empty($raw_options) ? $raw_options : $options;
                    foreach ($current_opts as $idx => $opt_val): 
                    ?>
                        <div class="option-row" style="display: flex; gap: 0.5rem; align-items: center;">
                            <input type="text" name="options[]" class="form-control" value="<?= e($opt_val) ?>" placeholder="Option <?= $idx + 1 ?>" required>
                            <?php if ($idx >= 2): ?>
                                <button type="button" class="btn btn-secondary btn-sm remove-option-btn">&times;</button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" id="addOptionBtn" class="btn btn-secondary btn-sm" style="margin-top: 0.75rem;">
                    + Add Another Option
                </button>
            </div>

            <div style="margin-top: 1.75rem; display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    Publish Poll
                </button>
                <a href="/dashboard.php" class="btn btn-secondary">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('optionsContainer');
    const addBtn = document.getElementById('addOptionBtn');

    addBtn.addEventListener('click', () => {
        const rowCount = container.querySelectorAll('.option-row').length;
        if (rowCount >= 10) {
            alert('A maximum of 10 options is allowed.');
            return;
        }

        const div = document.createElement('div');
        div.className = 'option-row';
        div.style.cssText = 'display: flex; gap: 0.5rem; align-items: center;';
        div.innerHTML = `
            <input type="text" name="options[]" class="form-control" placeholder="Option ${rowCount + 1}" required>
            <button type="button" class="btn btn-secondary btn-sm remove-option-btn">&times;</button>
        `;
        container.appendChild(div);

        // Bind remove button
        div.querySelector('.remove-option-btn').addEventListener('click', () => {
            div.remove();
        });
    });

    // Delegate remove button clicks for pre-existing rows
    container.addEventListener('click', (e) => {
        if (e.target && e.target.classList.contains('remove-option-btn')) {
            e.target.closest('.option-row').remove();
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
