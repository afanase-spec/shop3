<?php
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Чистим админскую сессию
unset(
    $_SESSION['admin_logged_in'],
    $_SESSION['admin_id'],
    $_SESSION['admin_username']
);

// Также разлогиниваем пользователя-админа полностью
unset(
    $_SESSION['user_logged_in'],
    $_SESSION['user_id'],
    $_SESSION['user_name'],
    $_SESSION['user_email'],
    $_SESSION['user_role']
);

// На всякий случай чистим всё
session_destroy();

redirect('/admin/login.php');