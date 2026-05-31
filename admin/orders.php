<?php
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Заказы - Админ-панель';
$db = getDB();
$message = '';
$error = '';

// Обработка изменения статуса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности';
    } else {
        $orderId = intval($_POST['order_id'] ?? 0);
        $status = $_POST['status'] ?? 'new';
        
        if (in_array($status, ['new', 'processing', 'delivered'])) {
            $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$status, $orderId]);
            $message = 'Статус заказа обновлен';
        }
    }
}

// Фильтры
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$dateFilter = isset($_GET['date']) ? $_GET['date'] : '';

// Просмотр деталей заказа
$viewOrder = null;
$orderItems = [];
if (isset($_GET['view'])) {
    $viewId = intval($_GET['view']);
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$viewId]);
    $viewOrder = $stmt->fetch();
    
    if ($viewOrder) {
        $stmt = $db->prepare("SELECT oi.*, p.name as product_name 
                             FROM order_items oi 
                             JOIN products p ON oi.product_id = p.id 
                             WHERE oi.order_id = ?");
        $stmt->execute([$viewId]);
        $orderItems = $stmt->fetchAll();
    }
}

// Список заказов
$orders = [];
if (!$viewOrder) {
    // Строим запрос с фильтрами
    $sql = "SELECT * FROM orders WHERE 1=1";
    $params = [];
    
    if (!empty($statusFilter)) {
        $sql .= " AND status = ?";
        $params[] = $statusFilter;
    }
    
    if ($dateFilter === 'today') {
        $sql .= " AND DATE(created_at) = CURDATE()";
    } elseif ($dateFilter === 'week') {
        $sql .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($dateFilter === 'month') {
        $sql .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
}

include __DIR__ . '/../templates/header.php';
?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="fw-bold">Управление заказами</h1>
        <?php if ($viewOrder): ?>
            <a href="<?= SITE_URL ?>/admin/orders.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Назад к списку
            </a>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?= escape($message) ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= escape($error) ?></div>
    <?php endif; ?>
    
    <!-- Просмотр деталей заказа -->
    <?php if ($viewOrder): ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <h3 class="fw-bold mb-4">Информация о заказе #<?= $viewOrder['id'] ?></h3>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="text-muted small">Клиент</label>
                            <p class="fw-semibold mb-0"><?= escape($viewOrder['customer_name']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Телефон</label>
                            <p class="fw-semibold mb-0"><?= escape($viewOrder['phone']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Дата заказа</label>
                            <p class="fw-semibold mb-0"><?= date('d.m.Y H:i', strtotime($viewOrder['created_at'])) ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small">Статус</label>
                            <p class="mb-0">
                                <?php
                                $statusClasses = [
                                    'new' => 'bg-primary',
                                    'processing' => 'bg-warning',
                                    'delivered' => 'bg-success'
                                ];
                                $statusNames = [
                                    'new' => 'Новый',
                                    'processing' => 'В обработке',
                                    'delivered' => 'Доставлен'
                                ];
                                ?>
                                <span class="badge <?= $statusClasses[$viewOrder['status']] ?>">
                                    <?= $statusNames[$viewOrder['status']] ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-12">
                            <label class="text-muted small">Адрес доставки</label>
                            <p class="fw-semibold mb-0"><?= nl2br(escape($viewOrder['address'])) ?></p>
                        </div>
                        <?php if (!empty($viewOrder['comment'])): ?>
                            <div class="col-12">
                                <label class="text-muted small">Комментарий</label>
                                <p class="mb-0"><?= nl2br(escape($viewOrder['comment'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <h4 class="fw-bold mb-3">Товары заказа</h4>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="bg-light">
                                <tr>
                                    <th>Товар</th>
                                    <th>Количество</th>
                                    <th>Цена за шт.</th>
                                    <th>Сумма</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td><?= escape($item['product_name']) ?></td>
                                        <td><?= $item['quantity'] ?></td>
                                        <td><?= formatPrice($item['price_at_time']) ?></td>
                                        <td><strong><?= formatPrice($item['price_at_time'] * $item['quantity']) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-light">
                                <tr>
                                    <td colspan="3" class="text-end fw-bold">Итого:</td>
                                    <td><strong class="fs-5"><?= formatPrice($viewOrder['total']) ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <h4 class="fw-bold mb-3">Изменить статус</h4>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="order_id" value="<?= $viewOrder['id'] ?>">
                        <input type="hidden" name="change_status" value="1">
                        
                        <select class="form-select mb-3" name="status">
                            <option value="new" <?= $viewOrder['status'] === 'new' ? 'selected' : '' ?>>Новый</option>
                            <option value="processing" <?= $viewOrder['status'] === 'processing' ? 'selected' : '' ?>>В обработке</option>
                            <option value="delivered" <?= $viewOrder['status'] === 'delivered' ? 'selected' : '' ?>>Доставлен</option>
                        </select>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Сохранить
                        </button>
                    </form>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Фильтры -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Статус</label>
                    <select class="form-select" name="status">
                        <option value="">Все статусы</option>
                        <option value="new" <?= $statusFilter === 'new' ? 'selected' : '' ?>>Новые</option>
                        <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : '' ?>>В обработке</option>
                        <option value="delivered" <?= $statusFilter === 'delivered' ? 'selected' : '' ?>>Доставлены</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Период</label>
                    <select class="form-select" name="date">
                        <option value="">Все время</option>
                        <option value="today" <?= $dateFilter === 'today' ? 'selected' : '' ?>>Сегодня</option>
                        <option value="week" <?= $dateFilter === 'week' ? 'selected' : '' ?>>Неделя</option>
                        <option value="month" <?= $dateFilter === 'month' ? 'selected' : '' ?>>Месяц</option>
                    </select>
                </div>
                
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>Применить фильтры
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Список заказов -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <?php if (empty($orders)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Заказов не найдено</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Дата</th>
                                    <th>Клиент</th>
                                    <th>Телефон</th>
                                    <th>Адрес</th>
                                    <th>Сумма</th>
                                    <th>Статус</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><strong>#<?= $order['id'] ?></strong></td>
                                        <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                                        <td><?= escape($order['customer_name']) ?></td>
                                        <td><?= escape($order['phone']) ?></td>
                                        <td>
                                            <small class="text-muted">
                                                <?= escape(mb_substr($order['address'], 0, 30)) ?>...
                                            </small>
                                        </td>
                                        <td><strong><?= formatPrice($order['total']) ?></strong></td>
                                        <td>
                                            <?php
                                            $statusClasses = [
                                                'new' => 'bg-primary',
                                                'processing' => 'bg-warning',
                                                'delivered' => 'bg-success'
                                            ];
                                            $statusNames = [
                                                'new' => 'Новый',
                                                'processing' => 'В обработке',
                                                'delivered' => 'Доставлен'
                                            ];
                                            ?>
                                            <span class="badge <?= $statusClasses[$order['status']] ?>">
                                                <?= $statusNames[$order['status']] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?view=<?= $order['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye me-1"></i>Просмотр
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
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
