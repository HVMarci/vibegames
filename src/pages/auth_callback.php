<?php
declare(strict_types=1);

$token = (string)($_GET['token'] ?? '');
if ($token === '') {
    flash_set('err', 'Missing token.');
    redirect('/login');
}

$userId = consume_login_token($token);
if (!$userId) {
    flash_set('err', 'Invalid or expired token.');
    redirect('/login');
}

$_SESSION['user_id'] = $userId;
flash_set('ok', 'Logged in.');
redirect('/vote');

