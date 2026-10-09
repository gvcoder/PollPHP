<?php
/**
 * Global Header Template
 * PollPHP
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$user = current_user();
$page_title = isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME . ' - Simple, Frictionless Polling';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?></title>
    
    <!-- OpenGraph / WhatsApp Meta Tags if set -->
    <?php if (isset($og_title)): ?>
        <meta property="og:title" content="<?= e($og_title) ?>">
        <meta property="og:description" content="<?= e($og_description ?? 'Cast your vote in this poll.') ?>">
        <meta property="og:type" content="website">
        <meta property="og:url" content="<?= e($og_url ?? APP_URL) ?>">
        <meta name="twitter:card" content="summary_large_image">
    <?php endif; ?>

    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<header class="navbar">
    <div class="container navbar-inner">
        <a href="/" class="brand">
            📊 <?= e(APP_NAME) ?>
            <span class="brand-badge">Fast & Simple</span>
        </a>

        <nav>
            <ul class="nav-links">
                <li><a href="/" class="nav-link">Home</a></li>
                <?php if ($user): ?>
                    <li><a href="/dashboard.php" class="nav-link">My Dashboard</a></li>
                    <li><a href="/create-poll.php" class="btn btn-primary btn-sm">+ New Poll</a></li>
                    <?php if ($user['role'] === 'admin'): ?>
                        <li><a href="/admin/index.php" class="badge badge-admin">Admin Area</a></li>
                    <?php endif; ?>
                    <li><a href="/logout.php" class="btn btn-secondary btn-sm">Logout (<?= e($user['name']) ?>)</a></li>
                <?php else: ?>
                    <li><a href="/login.php" class="nav-link">Sign In</a></li>
                    <li><a href="/register.php" class="btn btn-primary btn-sm">Get Started</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>

<main class="container" style="padding-top: 2rem; padding-bottom: 3rem;">
    <?php 
    $flash = get_flash(); 
    if ($flash): 
    ?>
        <div class="alert alert-<?= e($flash['type']) ?>">
            <span><?= e($flash['message']) ?></span>
        </div>
    <?php endif; ?>
