<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

switch ($path) {
    case '/':
        redirect('/vote');
        break;
    case '/register':
        require __DIR__ . '/../src/pages/register.php';
        break;
    case '/login':
        require __DIR__ . '/../src/pages/login.php';
        break;
    case '/auth/callback':
        require __DIR__ . '/../src/pages/auth_callback.php';
        break;
    case '/logout':
        require __DIR__ . '/../src/pages/logout.php';
        break;
    case '/entry':
        require __DIR__ . '/../src/pages/entry.php';
        break;
    case '/vote':
        require __DIR__ . '/../src/pages/vote.php';
        break;
    case '/results':
        require __DIR__ . '/../src/pages/results.php';
        break;
    case '/rules':
        require __DIR__ . '/../src/pages/rules.php';
        break;
    default:
        http_response_code(404);
        render('Not found', '<p>Not found.</p>');
}
