<?php
declare(strict_types=1);

$content = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $email = trim((string)($_POST['email'] ?? ''));
    $displayName = trim((string)($_POST['display_name'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash_set('err', 'Please enter a valid email address.');
        redirect('/register');
    }
    if ($displayName === '') {
        flash_set('err', 'Please enter a display name.');
        redirect('/register');
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        flash_set('err', 'This email is already registered. Please log in.');
        redirect('/login');
    }
    $ins = $pdo->prepare('INSERT INTO users (email, display_name) VALUES (?, ?)');
    $ins->execute([$email, $displayName]);
    flash_set('ok', 'Registered. You can now log in.');
    redirect('/login');
}

$content .= '<h1>Register</h1>';
$content .= '<p class="hint">Create an account using your email address (no password).</p>';
$content .= '<form method="post" class="grid">';
$content .= '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '" />';
$content .= '<div><label>Email</label><input type="email" name="email" required /></div>';
$content .= '<div><label>Display name</label><input type="text" name="display_name" required /></div>';
$content .= '<div><button class="btn" type="submit">Register</button></div>';
$content .= '</form>';

render('Register', $content);

