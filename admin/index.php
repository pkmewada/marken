<?php
require_once __DIR__ . '/includes/auth.php';

redirect(isLoggedIn() ? 'products' : 'login');
