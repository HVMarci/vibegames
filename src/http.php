<?php
declare(strict_types=1);

function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function base_url(): string
{
    $base = rtrim(env('APP_BASE_URL', ''), '/');
    if ($base !== '') {
        return $base;
    }
    $scheme = (($_SERVER['HTTPS'] ?? 'off') !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function flash_set(string $key, string $message): void
{
    $_SESSION['_flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    $msg = $_SESSION['_flash'][$key] ?? null;
    if ($msg !== null) {
        unset($_SESSION['_flash'][$key]);
    }
    return $msg;
}

function render(string $title, string $contentHtml): void
{
    $user = current_user();
    $ok = flash_get('ok');
    $err = flash_get('err');
    require __DIR__ . '/views/layout.php';
    exit;
}

