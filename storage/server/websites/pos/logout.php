<?php
require_once __DIR__ . '/config.php';
$params = session_get_cookie_params();
setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
unset($_SESSION['user_id']);
unset($_SESSION['user_name']);
unset($_SESSION['user_role']);
unset($_SESSION['user_username']);
unset($_SESSION['flash']);
redirect('?page=login');
