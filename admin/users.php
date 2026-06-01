<?php
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Пользователи - Админ-панель';
$db = getDB();

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
    
    // Проверка уникальности email (если меняется)
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $userId]);
    if ($stmt->fetch()) {
        setFlashMessage('Этот email уже используется другим пользователем', 'danger');
        redirect('/admin/users.php?view=' . $userId);
    }
    
    try {
        $stmt = $db->prepare("
            UPDATE users 
            SET name = ?, email = ?, phone = ?, address = ?, email_notifications = ? 
            WHERE id = ?
        ");
        $stmt->execute([$name, $email, $phone, $address, $emailNotif, $userId]);
        setFlashMessage('Данные пользователя обновлены', 'success');
    } catch (Exception $e) {
        error_log("Ошибка обновления пользователя #$userId: " . $e->getMessage());
        setFlashMessage('Ошибка при сохранении данных', 'danger');
    }
    
    redirect('/admin/users.php?view=' . $userId);
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
    
    try {
        // Не удаляем заказы — просто отвязываем user_id (orders.user_id станет NULL)
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
    // Загружаем юзера
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$viewId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        setFlashMessage('Пользователь не найден', 'warning');
        redirect('/admin/users.php');
    }
    
    // Заказы юзера (по user_id, плюс старые без user_id по совпадению имени+телефона)
    $stmt = $db->prepare("
        SELECT * FROM orders 
        WHERE user_id = ? 
           OR (user_id IS NULL AND customer_name = ? AND phone = ? AND phone != '')
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user['id'], $user['name'], $user['phone'] ?? '']);
    $orders = $stmt->fetchAll();
    
    // Статистика
    $totalSpent = 0;
    foreach ($orders as $order) {
        $totalSpent += floatval($order['total']);
    }
    
    // История email-уведомлений (если таблица существует)
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
    } catch (Exception $e) {
        // Таблицы email_log может не быть — это нормально
    }
    
    include __DIR__ . '/../templates/header.php';
    ?>
    
    <div class="container my-5">
        <!-- Хлебные крошки -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <a href="<?= SITE_URL ?>/admin/users.php" class="text-muted text-decoration-none">
                    <i class="fas fa-arrow-left me-2"></i>К списку пользователей
                </a>
                <h1 class="mt-3 mb-0">
                    <i class="fas fa-user-circle me-2 text-primary"></i>
                    <?= escape($user['name']) ?>
                </h1>
                <small class="text-muted">ID: #<?= $user['id'] ?> · Зарегистрирован <?= date('d.m.Y H:i', strtotime($user['created_at'])) ?></small>
            </div>
            <div>
                <!-- Удаление -->
                <button type="button" class="btn btn-outline-danger" 
                        data-bs-toggle="modal" data-bs-target="#deleteUserModal">
                    <i class="fas fa-trash me-2"></i>Удалить пользователя
                </button>
            </div>
        </div>
        
        <?php $flash = getFlashMessage(); if ($flash): ?>
            <div class="alert alert-<?= escape($flash['type']) ?>"><?= escape($flash['message']) ?></div>
        <?php endif; ?>
        
        <div class="row g-4">
            <!-- ЛЕВАЯ КОЛОНКА: данные + сброс пароля -->
            <div class="col-lg-7">
                <!-- Редактирование данных -->
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
                
                <!-- Сброс пароля -->
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
            
            <!-- ПРАВАЯ КОЛОНКА: статистика + email log -->
            <div class="col-lg-5">
                <!-- Статистика -->
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
                
                <!-- История email-уведомлений -->
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
        
        <!-- ИСТОРИЯ ЗАКАЗОВ -->
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
    
    <!-- Модалка удаления -->
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
                        Заказы пользователя <strong>сохранятся</strong> в системе, но станут «анонимными» — связь с аккаунтом будет разорвана. Это действие нельзя отменить.
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
    
    <?php
    include __DIR__ . '/../templates/footer.php';
    exit;
}

// ============================================
// РЕЖИМ СПИСКА ПОЛЬЗОВАТЕЛЕЙ
// ============================================
$searchQuery = trim($_GET['q'] ?? '');
$notifFilter = $_GET['notif'] ?? '';

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

$sql .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Общая статистика
$stats = $db->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN email_notifications = 1 THEN 1 ELSE 0 END) AS with_notif,
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
    
    <!-- Статистика -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 text-center">
                <div class="text-muted small">Всего пользователей</div>
                <div class="fs-2 fw-bold text-primary"><?= (int)$stats['total'] ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 text-center">
                <div class="text-muted small">🔔 С включёнными уведомлениями</div>
                <div class="fs-2 fw-bold text-success"><?= (int)$stats['with_notif'] ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 text-center">
                <div class="text-muted small">🆕 Новые за неделю</div>
                <div class="fs-2 fw-bold text-info"><?= (int)$stats['new_week'] ?></div>
            </div>
        </div>
    </div>
    
    <!-- Фильтры/поиск -->
    <div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label small mb-1">Поиск</label>
                <input type="text" name="q" value="<?= escape($searchQuery) ?>"
                       placeholder="Имя, email или телефон..." class="form-control form-control-sm">
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
                <?php if (!empty($searchQuery) || !empty($notifFilter)): ?>
                    <a href="<?= SITE_URL ?>/admin/users.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Таблица -->
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
                                <th>Телефон</th>
                                <th class="text-center">Заказов</th>
                                <th class="text-end">Потрачено</th>
                                <th class="text-center">🔔</th>
                                <th>Регистрация</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><span class="text-muted">#<?= $u['id'] ?></span></td>
                                    <td>
                                        <a href="<?= SITE_URL ?>/admin/users.php?view=<?= (int)$u['id'] ?>" 
                                           class="text-decoration-none fw-semibold">
                                            <?= escape($u['name']) ?>
                                        </a>
                                    </td>
                                    <td><small><?= escape($u['email']) ?></small></td>
                                    <td><small><?= escape($u['phone'] ?? '—') ?></small></td>
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
                                            <span class="text-success" title="Уведомления включены"><i class="fas fa-check-circle"></i></span>
                                        <?php else: ?>
                                            <span class="text-muted" title="Отключены"><i class="fas fa-times-circle"></i></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?= date('d.m.Y', strtotime($u['created_at'])) ?></small></td>
                                    <td class="text-end">
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