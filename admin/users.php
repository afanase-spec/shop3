<?php
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Пользователи - Админ-панель';
$db = getDB();

// ID текущего админа (для защиты от снятия роли с самого себя)
$currentAdminType = $CURRENT_ADMIN_TYPE ?? 'classic';
$currentAdminId   = $CURRENT_ADMIN_ID   ?? null;

// ============================================
// ОБРАБОТКА POST: РЕДАКТИРОВАНИЕ ПОЛЬЗОВАТЕЛЯ
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('Ошибка безопасности', 'danger');
        redirect('/admin/users.php');
    }
    
    $userId  = (int)($_POST['user_id'] ?? 0);
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $emailNotif = isset($_POST['email_notifications']) ? 1 : 0;
    $role    = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
    
    // Валидация
    if ($userId <= 0) {
        setFlashMessage('Некорректный ID пользователя', 'danger');
        redirect('/admin/users.php');
    }
    if (empty($name) || mb_strlen($name) < 2) {
        setFlashMessage('Имя должно быть не короче 2 символов', 'danger');
        redirect('/admin/users.php?view=' . $userId);
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setFlashMessage('Некорректный email', 'danger');
        redirect('/admin/users.php?view=' . $userId);
    }
    
    // ЗАЩИТА: пользователь-админ не может снять с себя роль через форму
    if ($currentAdminType === 'user' && $currentAdminId === $userId && $role === 'user') {
        setFlashMessage('⛔ Нельзя снять роль администратора с самого себя. Попросите главного администратора.', 'warning');
        redirect('/admin/users.php?view=' . $userId);
    }
    
    // Проверка уникальности email
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $userId]);
    if ($stmt->fetch()) {
        setFlashMessage('Этот email уже используется другим пользователем', 'danger');
        redirect('/admin/users.php?view=' . $userId);
    }
    
    try {
        $stmt = $db->prepare("
            UPDATE users 
            SET name = ?, email = ?, phone = ?, address = ?, role = ?, email_notifications = ? 
            WHERE id = ?
        ");
        $stmt->execute([$name, $email, $phone, $address, $role, $emailNotif, $userId]);
        setFlashMessage('Данные пользователя обновлены', 'success');
    } catch (Exception $e) {
        error_log("Ошибка обновления пользователя #$userId: " . $e->getMessage());
        setFlashMessage('Ошибка при сохранении данных', 'danger');
    }
    
    redirect('/admin/users.php?view=' . $userId);
}

// ============================================
// ОБРАБОТКА POST: БЫСТРАЯ СМЕНА РОЛИ (кнопка из таблицы)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_role'])) {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('Ошибка безопасности (CSRF)', 'danger');
        redirect('/admin/users.php');
    }
    
    $targetUserId = (int)($_POST['user_id'] ?? 0);
    $newRole      = ($_POST['new_role'] ?? '') === 'admin' ? 'admin' : 'user';
    
    if ($targetUserId <= 0) {
        setFlashMessage('Некорректный ID пользователя', 'danger');
        redirect('/admin/users.php');
    }
    
    // ЗАЩИТА: пользователь-админ не может снять роль сам с себя
    if ($currentAdminType === 'user' && $currentAdminId === $targetUserId && $newRole === 'user') {
        setFlashMessage('⛔ Нельзя снять роль администратора с самого себя', 'warning');
        redirect('/admin/users.php');
    }
    
    $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
    $ok = $stmt->execute([$newRole, $targetUserId]);
    
    if ($ok) {
        $msg = $newRole === 'admin'
            ? '✅ Роль администратора назначена. Пользователю доступна админ-панель после следующего входа.'
            : '✅ Роль администратора снята. Доступ к админ-панели закрыт.';
        setFlashMessage($msg, 'success');
    } else {
        setFlashMessage('Не удалось обновить роль', 'danger');
    }
    
    redirect('/admin/users.php');
}

// ============================================
// ОБРАБОТКА POST: СБРОС ПАРОЛЯ
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('Ошибка безопасности', 'danger');
        redirect('/admin/users.php');
    }
    
    $userId      = (int)($_POST['user_id'] ?? 0);
    $newPassword = $_POST['new_password'] ?? '';
    
    if (strlen($newPassword) < 6) {
        setFlashMessage('Пароль должен быть не менее 6 символов', 'danger');
        redirect('/admin/users.php?view=' . $userId);
    }
    
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([$newHash, $userId]);
    setFlashMessage('Пароль успешно изменён', 'success');
    redirect('/admin/users.php?view=' . $userId);
}

// ============================================
// ОБРАБОТКА POST: УДАЛЕНИЕ ПОЛЬЗОВАТЕЛЯ
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('Ошибка безопасности', 'danger');
        redirect('/admin/users.php');
    }
    
    $userId = (int)($_POST['user_id'] ?? 0);
    
    // ЗАЩИТА: нельзя удалить самого себя
    if ($currentAdminType === 'user' && $currentAdminId === $userId) {
        setFlashMessage('⛔ Нельзя удалить самого себя', 'warning');
        redirect('/admin/users.php');
    }
    
    try {
        $stmt = $db->prepare("UPDATE orders SET user_id = NULL WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        setFlashMessage('Пользователь удалён. Его заказы сохранены в системе.', 'success');
    } catch (Exception $e) {
        error_log("Ошибка удаления пользователя #$userId: " . $e->getMessage());
        setFlashMessage('Ошибка при удалении пользователя', 'danger');
    }
    
    redirect('/admin/users.php');
}

// ============================================
// РЕЖИМ ПРОСМОТРА КОНКРЕТНОГО ПОЛЬЗОВАТЕЛЯ
// ============================================
$viewId = isset($_GET['view']) ? (int)$_GET['view'] : 0;

if ($viewId > 0) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$viewId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        setFlashMessage('Пользователь не найден', 'warning');
        redirect('/admin/users.php');
    }
    
    $stmt = $db->prepare("
        SELECT * FROM orders 
        WHERE user_id = ? 
           OR (user_id IS NULL AND customer_name = ? AND phone = ? AND phone != '')
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user['id'], $user['name'], $user['phone'] ?? '']);
    $orders = $stmt->fetchAll();
    
    $totalSpent = 0;
    foreach ($orders as $order) {
        $totalSpent += floatval($order['total']);
    }
    
    $emailLogs = [];
    try {
        $stmt = $db->prepare("
            SELECT * FROM email_log 
            WHERE recipient = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$user['email']]);
        $emailLogs = $stmt->fetchAll();
    } catch (Exception $e) {}
    
    $isSelf = ($currentAdminType === 'user' && $currentAdminId === (int)$user['id']);
    
    include __DIR__ . '/../templates/header.php';
    ?>
    
    <div class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <a href="<?= SITE_URL ?>/admin/users.php" class="text-muted text-decoration-none">
                    <i class="fas fa-arrow-left me-2"></i>К списку пользователей
                </a>
                <h1 class="mt-3 mb-0">
                    <i class="fas fa-user-circle me-2 text-primary"></i>
                    <?= escape($user['name']) ?>
                    <?php if (($user['role'] ?? 'user') === 'admin'): ?>
                        <span class="badge bg-danger fs-6 align-middle ms-2">
                            <i class="fas fa-shield-alt me-1"></i>Админ
                        </span>
                    <?php endif; ?>
                    <?php if ($isSelf): ?>
                        <span class="badge bg-info text-dark fs-6 align-middle ms-2">Это вы</span>
                    <?php endif; ?>
                </h1>
                <small class="text-muted">ID: #<?= $user['id'] ?> · Зарегистрирован <?= date('d.m.Y H:i', strtotime($user['created_at'])) ?></small>
            </div>
            <div>
                <?php if (!$isSelf): ?>
                    <button type="button" class="btn btn-outline-danger" 
                            data-bs-toggle="modal" data-bs-target="#deleteUserModal">
                        <i class="fas fa-trash me-2"></i>Удалить пользователя
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <?php $flash = getFlashMessage(); if ($flash): ?>
            <div class="alert alert-<?= escape($flash['type']) ?>"><?= escape($flash['message']) ?></div>
        <?php endif; ?>
        
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                    <h4 class="fw-bold mb-4"><i class="fas fa-edit me-2 text-primary"></i>Личные данные</h4>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                        <input type="hidden" name="update_user" value="1">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">ФИО <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" required minlength="2"
                                       value="<?= escape($user['name']) ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" required
                                       value="<?= escape($user['email']) ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Телефон</label>
                                <input type="tel" class="form-control" name="phone"
                                       value="<?= escape($user['phone'] ?? '') ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Дата регистрации</label>
                                <input type="text" class="form-control" disabled
                                       value="<?= date('d.m.Y H:i', strtotime($user['created_at'])) ?>">
                            </div>
                            
                            <!-- НОВОЕ: РОЛЬ -->
                            <div class="col-12">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-user-shield me-2 text-primary"></i>Роль пользователя
                                </label>
                                <select class="form-select" name="role" <?= $isSelf ? 'disabled' : '' ?>>
                                    <option value="user"  <?= ($user['role'] ?? 'user') === 'user'  ? 'selected' : '' ?>>
                                        👤 Пользователь (обычный доступ)
                                    </option>
                                    <option value="admin" <?= ($user['role'] ?? 'user') === 'admin' ? 'selected' : '' ?>>
                                        🛡️ Администратор (полный доступ к админ-панели)
                                    </option>
                                </select>
                                <?php if ($isSelf): ?>
                                    <small class="text-warning">
                                        <i class="fas fa-lock me-1"></i>Вы не можете изменить свою роль. 
                                        Это сделает главный администратор.
                                    </small>
                                    <input type="hidden" name="role" value="<?= escape($user['role'] ?? 'user') ?>">
                                <?php else: ?>
                                    <small class="text-muted">
                                        Администраторы могут заходить в /admin/* и управлять всем сайтом.
                                    </small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label fw-semibold">Адрес доставки</label>
                                <textarea class="form-control" name="address" rows="2"><?= escape($user['address'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="col-12">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" role="switch" 
                                           id="emailNotif" name="email_notifications" value="1"
                                           <?= intval($user['email_notifications']) === 1 ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold ms-2" for="emailNotif">
                                        <i class="fas fa-bell me-1"></i>Email-уведомления
                                        <small class="text-muted ms-2">
                                            (<?= intval($user['email_notifications']) === 1 ? 'включены' : 'отключены' ?>)
                                        </small>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary mt-4">
                            <i class="fas fa-save me-2"></i>Сохранить изменения
                        </button>
                    </form>
                </div>
                
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <h4 class="fw-bold mb-3"><i class="fas fa-key me-2 text-warning"></i>Сбросить пароль</h4>
                    <p class="text-muted small mb-3">Установите пользователю новый пароль. Сообщите его клиенту лично.</p>
                    
                    <form method="POST" action="" onsubmit="return confirm('Подтвердить смену пароля?');">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                        <input type="hidden" name="reset_password" value="1">
                        
                        <div class="input-group">
                            <input type="text" class="form-control" name="new_password" 
                                   placeholder="Новый пароль (мин. 6 символов)" minlength="6" required>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key me-2"></i>Сбросить
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                    <h4 class="fw-bold mb-3"><i class="fas fa-chart-bar me-2 text-primary"></i>Статистика</h4>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Всего заказов:</span>
                        <strong class="fs-5"><?= count($orders) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Потрачено:</span>
                        <strong class="text-success fs-5"><?= formatPrice($totalSpent) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Средний чек:</span>
                        <strong><?= count($orders) > 0 ? formatPrice($totalSpent / count($orders)) : formatPrice(0) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Последний заказ:</span>
                        <strong>
                            <?= !empty($orders) ? date('d.m.Y', strtotime($orders[0]['created_at'])) : '—' ?>
                        </strong>
                    </div>
                </div>
                
                <?php if (!empty($emailLogs)): ?>
                    <div class="card border-0 shadow-sm rounded-4 p-4">
                        <h4 class="fw-bold mb-3"><i class="fas fa-envelope me-2 text-primary"></i>Письма (последние 10)</h4>
                        <div class="list-group list-group-flush">
                            <?php foreach ($emailLogs as $log): ?>
                                <div class="list-group-item px-0 py-2 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="me-2" style="min-width:0;flex:1;">
                                            <small class="d-block text-truncate"><?= escape($log['subject']) ?></small>
                                            <small class="text-muted"><?= date('d.m.Y H:i', strtotime($log['created_at'])) ?></small>
                                        </div>
                                        <?php
                                        $statusBadges = [
                                            'sent'   => ['bg-success', '✅'],
                                            'logged' => ['bg-info', '📝'],
                                            'failed' => ['bg-danger', '❌']
                                        ];
                                        $badge = $statusBadges[$log['status']] ?? ['bg-secondary', '?'];
                                        ?>
                                        <span class="badge <?= $badge[0] ?>"><?= $badge[1] ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm rounded-4 mt-4">
            <div class="card-header bg-white border-0 py-3">
                <h4 class="fw-bold mb-0">
                    <i class="fas fa-history me-2 text-primary"></i>
                    История заказов
                    <span class="badge bg-light text-dark ms-2"><?= count($orders) ?></span>
                </h4>
            </div>
            <div class="card-body p-0">
                <?php if (empty($orders)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-0">У пользователя пока нет заказов</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>№</th>
                                    <th>Дата</th>
                                    <th>Сумма</th>
                                    <th>Статус</th>
                                    <th>Адрес</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $statusClasses = [
                                    'new'        => 'bg-primary',
                                    'processing' => 'bg-warning',
                                    'delivered'  => 'bg-success'
                                ];
                                $statusNames = [
                                    'new'        => 'Новый',
                                    'processing' => 'В обработке',
                                    'delivered'  => 'Доставлен'
                                ];
                                foreach ($orders as $order):
                                ?>
                                    <tr>
                                        <td><strong>#<?= $order['id'] ?></strong></td>
                                        <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                                        <td><strong><?= formatPrice($order['total']) ?></strong></td>
                                        <td>
                                            <span class="badge <?= $statusClasses[$order['status']] ?? 'bg-secondary' ?>">
                                                <?= $statusNames[$order['status']] ?? escape($order['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?= escape(mb_substr($order['address'] ?? '', 0, 40)) ?>
                                                <?= mb_strlen($order['address'] ?? '') > 40 ? '…' : '' ?>
                                            </small>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= SITE_URL ?>/admin/orders.php?view=<?= (int)$order['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if (!$isSelf): ?>
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>Удалить пользователя?
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Вы собираетесь удалить пользователя <strong><?= escape($user['name']) ?></strong> (<?= escape($user['email']) ?>).</p>
                    <div class="alert alert-warning small mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Заказы пользователя <strong>сохранятся</strong> в системе, но станут «анонимными». Это действие нельзя отменить.
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                    <form method="POST" action="" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                        <input type="hidden" name="delete_user" value="1">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Да, удалить
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php
    include __DIR__ . '/../templates/footer.php';
    exit;
}

// ============================================
// РЕЖИМ СПИСКА ПОЛЬЗОВАТЕЛЕЙ
// ============================================
$searchQuery = trim($_GET['q'] ?? '');
$notifFilter = $_GET['notif'] ?? '';
$roleFilter  = $_GET['role']  ?? '';

$sql = "
    SELECT u.*, 
           (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS orders_count,
           (SELECT COALESCE(SUM(o.total), 0) FROM orders o WHERE o.user_id = u.id) AS total_spent,
           (SELECT MAX(o.created_at) FROM orders o WHERE o.user_id = u.id) AS last_order_at
    FROM users u
    WHERE 1=1
";
$params = [];

if (!empty($searchQuery)) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $likePattern = '%' . $searchQuery . '%';
    $params[] = $likePattern;
    $params[] = $likePattern;
    $params[] = $likePattern;
}

if ($notifFilter === 'on') {
    $sql .= " AND u.email_notifications = 1";
} elseif ($notifFilter === 'off') {
    $sql .= " AND u.email_notifications = 0";
}

if ($roleFilter === 'admin') {
    $sql .= " AND u.role = 'admin'";
} elseif ($roleFilter === 'user') {
    $sql .= " AND u.role = 'user'";
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$stats = $db->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN email_notifications = 1 THEN 1 ELSE 0 END) AS with_notif,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) AS admins_count,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS new_week
    FROM users
")->fetch();

include __DIR__ . '/../templates/header.php';
?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="mb-0">
            <i class="fas fa-users me-2 text-primary"></i>
            Пользователи
        </h1>
        <a href="<?= SITE_URL ?>/admin/index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>В админку
        </a>
    </div>
    
    <?php $flash = getFlashMessage(); if ($flash): ?>
        <div class="alert alert-<?= escape($flash['type']) ?>"><?= escape($flash['message']) ?></div>
    <?php endif; ?>
    
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 text-center">
                <div class="text-muted small">Всего пользователей</div>
                <div class="fs-2 fw-bold text-primary"><?= (int)$stats['total'] ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 text-center">
                <div class="text-muted small">🛡️ Администраторы</div>
                <div class="fs-2 fw-bold text-danger"><?= (int)$stats['admins_count'] ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 text-center">
                <div class="text-muted small">🔔 С уведомлениями</div>
                <div class="fs-2 fw-bold text-success"><?= (int)$stats['with_notif'] ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 text-center">
                <div class="text-muted small">🆕 Новые за неделю</div>
                <div class="fs-2 fw-bold text-info"><?= (int)$stats['new_week'] ?></div>
            </div>
        </div>
    </div>
    
    <div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">Поиск</label>
                <input type="text" name="q" value="<?= escape($searchQuery) ?>"
                       placeholder="Имя, email или телефон..." class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Роль</label>
                <select name="role" class="form-select form-select-sm">
                    <option value="">Все</option>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>🛡️ Админы</option>
                    <option value="user"  <?= $roleFilter === 'user'  ? 'selected' : '' ?>>👤 Пользователи</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Уведомления</label>
                <select name="notif" class="form-select form-select-sm">
                    <option value="">Все</option>
                    <option value="on"  <?= $notifFilter === 'on'  ? 'selected' : '' ?>>🔔 Включены</option>
                    <option value="off" <?= $notifFilter === 'off' ? 'selected' : '' ?>>🔕 Отключены</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-fill">
                    <i class="fas fa-search me-1"></i>Найти
                </button>
                <?php if (!empty($searchQuery) || !empty($notifFilter) || !empty($roleFilter)): ?>
                    <a href="<?= SITE_URL ?>/admin/users.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <?php if (empty($users)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                    <p class="text-muted">
                        <?= !empty($searchQuery) ? 'Ничего не найдено' : 'Пока нет зарегистрированных пользователей' ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>ID</th>
                                <th>Пользователь</th>
                                <th>Email</th>
                                <th>Роль</th>
                                <th class="text-center">Заказов</th>
                                <th class="text-end">Потрачено</th>
                                <th class="text-center">🔔</th>
                                <th>Регистрация</th>
                                <th class="text-end">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): 
                                $userRole = $u['role'] ?? 'user';
                                $isSelfRow = ($currentAdminType === 'user' && $currentAdminId === (int)$u['id']);
                            ?>
                                <tr>
                                    <td><span class="text-muted">#<?= $u['id'] ?></span></td>
                                    <td>
                                        <a href="<?= SITE_URL ?>/admin/users.php?view=<?= (int)$u['id'] ?>" 
                                           class="text-decoration-none fw-semibold">
                                            <?= escape($u['name']) ?>
                                        </a>
                                        <?php if ($isSelfRow): ?>
                                            <small class="badge bg-info text-dark ms-1">вы</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?= escape($u['email']) ?></small></td>
                                    <td>
                                        <?php if ($userRole === 'admin'): ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-shield-alt me-1"></i>Админ
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-user me-1"></i>Пользователь
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ((int)$u['orders_count'] > 0): ?>
                                            <span class="badge bg-light text-dark"><?= (int)$u['orders_count'] ?></span>
                                        <?php else: ?>
                                            <small class="text-muted">—</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ((float)$u['total_spent'] > 0): ?>
                                            <strong class="text-success"><?= formatPrice($u['total_spent']) ?></strong>
                                        <?php else: ?>
                                            <small class="text-muted">—</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (intval($u['email_notifications']) === 1): ?>
                                            <span class="text-success" title="Включены"><i class="fas fa-check-circle"></i></span>
                                        <?php else: ?>
                                            <span class="text-muted" title="Отключены"><i class="fas fa-times-circle"></i></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?= date('d.m.Y', strtotime($u['created_at'])) ?></small></td>
                                    <td class="text-end">
                                        <!-- Кнопка смены роли -->
                                        <?php if ($userRole === 'admin'): ?>
                                            <?php if (!$isSelfRow): ?>
                                                <form method="POST" action="" class="d-inline" 
                                                      onsubmit="return confirm('Снять роль администратора с пользователя <?= escape($u['name']) ?>?');">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                                    <input type="hidden" name="new_role" value="user">
                                                    <input type="hidden" name="toggle_role" value="1">
                                                    <button type="submit" 
                                                            class="btn btn-sm btn-outline-warning" 
                                                            title="Снять админа">
                                                        <i class="fas fa-user-minus"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <form method="POST" action="" class="d-inline" 
                                                  onsubmit="return confirm('Назначить пользователя <?= escape($u['name']) ?> администратором? Он получит полный доступ к админ-панели.');">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                                <input type="hidden" name="new_role" value="admin">
                                                <input type="hidden" name="toggle_role" value="1">
                                                <button type="submit" 
                                                        class="btn btn-sm btn-outline-success" 
                                                        title="Сделать админом">
                                                    <i class="fas fa-user-shield"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <a href="<?= SITE_URL ?>/admin/users.php?view=<?= (int)$u['id'] ?>" 
                                           class="btn btn-sm btn-outline-primary" title="Открыть">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="p-3 text-muted small">
                    Показано: <?= count($users) ?> 
                    <?= !empty($searchQuery) ? '(по запросу «' . escape($searchQuery) . '»)' : '' ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>