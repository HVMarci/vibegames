<?php
declare(strict_types=1);

if (current_user()) {
    redirect('/vote');
}

$content = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $email = trim((string)($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash_set('err', 'Please enter a valid email address.');
        redirect('/login');
    }
    $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        flash_set('err', 'No account found for that email. Please register first.');
        redirect('/register');
    }

    $token = create_login_token((int)$user['id'], 30);
    $link = base_url() . '/auth/callback?token=' . urlencode($token);

    if (env('APP_ENV', 'dev') === 'dev') {
        $content .= '<h1>Login link</h1>';
        $content .= '<p class="hint">Dev mode: email is not sent. Use this link:</p>';
        $content .= '<p><a href="' . h($link) . '">' . h($link) . '</a></p>';
        $content .= '<p><a href="/login">Back</a></p>';
        render('Login link', $content);
    }

    $from = env('MAIL_FROM', 'no-reply@example.com');
    $subject = 'Your login link';
    $body = "Click to log in:\n\n{$link}\n\nThis link expires in 30 minutes.";
    $headers = "From: {$from}\r\n";
    @mail($email, $subject, $body, $headers);

    flash_set('ok', 'Login email sent (if mail is configured).');
    redirect('/login');
}

$content .= '<h1>Login</h1>';
$content .= '<p class="hint">Enter your email address to receive a magic login link.</p>';
$content .= '<form method="post" class="grid">';
$content .= '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '" />';
$content .= '<div><label>Email</label><input type="email" name="email" required /></div>';
$content .= '<div><button class="btn" type="submit">Send login link</button></div>';
$content .= '</form>';
$content .= '<p class="hint">No account? <a href="/register">Register here</a>.</p>';

render('Login', $content);
