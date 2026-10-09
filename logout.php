<?php
/**
 * User Logout Page
 * PollPHP
 */
require_once __DIR__ . '/includes/auth.php';

logout_user();
set_flash('info', 'You have been signed out.');
redirect('/login.php');
