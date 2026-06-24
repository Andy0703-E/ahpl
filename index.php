<?php
require_once __DIR__ . '/config/config.php';
initDatabase();
header('Location: /panel/login.php');
exit;
