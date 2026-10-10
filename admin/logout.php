<?php
require_once __DIR__ . '/includes/auth.php';

startSession();
$_SESSION = [];
$p = session_get_cookie_params();
setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
session_destroy();
redirect('login');
