<?php
require_once __DIR__ . '/functions.php';

/**
 * Проверка доступа к админ-панели.
 * Доступ имеют:
 *   1. Классические администраторы (таблица admins, $_SESSION['admin_logged_in'])
 *   2. Обычные пользователи с ролью 'admin' (таблица users, $_SESSION['user_role'] = 'admin')
 */

$isClassicAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$isUserAdmin    = isset($_SESSION['user_logged_in']) 
                  && $_SESSION['user_logged_in'] === true
                  && ($_SESSION['user_role'] ?? 'user') === 'admin';

if (!$isClassicAdmin && !$isUserAdmin) {
    redirect('/admin/login.php');
}

// Глобальные переменные для шапки админки и проверок в users.php
$CURRENT_ADMIN_TYPE = $isClassicAdmin ? 'classic' : 'user';
$CURRENT_ADMIN_ID   = $isClassicAdmin
    ? ($_SESSION['admin_id'] ?? null)
    : (int)($_SESSION['user_id'] ?? 0);
$CURRENT_ADMIN_NAME = $isClassicAdmin
    ? 'Главный администратор'
    : ($_SESSION['user_name'] ?? 'Пользователь-админ');