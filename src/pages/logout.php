<?php
declare(strict_types=1);

flash_set('ok', 'Logged out.');
unset($_SESSION['user_id']);
session_regenerate_id(true);
redirect('/login');
