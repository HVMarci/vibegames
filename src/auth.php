<?php
declare(strict_types=1);

function current_user(): ?array
{
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id) && !ctype_digit((string)$id)) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, email, display_name, is_results_viewer FROM users WHERE id = ?');
    $stmt->execute([(int)$id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        flash_set('err', 'Please log in.');
        redirect('/login');
    }
    return $user;
}

function require_results_viewer(array $user): void
{
    if ((int)($user['is_results_viewer'] ?? 0) !== 1) {
        http_response_code(403);
        render('Forbidden', '<p>Forbidden.</p>');
    }
}

function create_login_token(int $userId, int $minutesValid = 30): string
{
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $stmt = db()->prepare('INSERT INTO login_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))');
    $stmt->execute([$userId, $hash, $minutesValid]);
    return $token;
}

function consume_login_token(string $token): ?int
{
    $hash = hash('sha256', $token);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id, user_id FROM login_tokens WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() FOR UPDATE');
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        if (!$row) {
            $pdo->rollBack();
            return null;
        }
        $upd = $pdo->prepare('UPDATE login_tokens SET used_at = NOW() WHERE id = ?');
        $upd->execute([(int)$row['id']]);
        $pdo->commit();
        return (int)$row['user_id'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

