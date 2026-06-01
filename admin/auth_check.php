<?php
/**
 * Проверка доступа к админ-панели.
 * Доступ имеют:
 *   1. Классические администраторы (таблица admins, $_SESSION['admin_logged_in'])
 *   2. Обычные пользователи с ролью 'admin' (таблица users, $_SESSION['user_role'] = 'admin')
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/functions.php';

$isClassicAdmin = !empty($_SESSION['admin_logged_in']);
$isUserAdmin    = !empty($_SESSION['user_logged_in'])
                  && ($_SESSION['user_role'] ?? 'user') === 'admin';

if (!$isClassicAdmin && !$isUserAdmin) {
    redirect('/admin/login.php');
    exit;
}

// Удобные глобальные переменные для шапки админки
$CURRENT_ADMIN_TYPE = $isClassicAdmin ? 'classic' : 'user';
$CURRENT_ADMIN_ID   = $isClassicAdmin
    ? ($_SESSION['admin_id'] ?? null)
    : ($_SESSION['user_id'] ?? null);
$CURRENT_ADMIN_NAME = $isClassicAdmin
    ? 'Главный администратор'
    : ($_SESSION['user_name'] ?? 'Пользователь-админ');