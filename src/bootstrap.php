<?php
declare(strict_types=1);

require __DIR__ . '/polyfills.php';
require __DIR__ . '/env.php';
require __DIR__ . '/db.php';
require __DIR__ . '/http.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/csrf.php';
require __DIR__ . '/uploads.php';

load_env(__DIR__ . '/../.env');

ini_set('display_errors', env('APP_ENV', 'dev') === 'dev' ? '1' : '0');
error_reporting(E_ALL);

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => (($_SERVER['HTTPS'] ?? 'off') !== 'off') || str_starts_with(env('APP_BASE_URL', ''), 'https://'),
]);
session_start();
