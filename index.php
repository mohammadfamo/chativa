<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

redirect(is_logged_in() ? 'chat.php' : 'login.php');
