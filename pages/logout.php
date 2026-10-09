<?php
/**
 * Logout do sistema
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

session_unset();
session_destroy();
header('Location: ' . PAGES_URL . 'login.php');
exit();
