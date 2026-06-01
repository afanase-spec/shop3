<?php
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Дашборд - Админ-панель';
$db = getDB();

// Статистика
$todayOrders = $db->query("SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = CURDATE()")->fetch()['count'];
$todayRevenue = $db->query("SELECT COALESCE(SUM(total), 0) as sum FROM orders WHERE DATE(created_at) = CURDATE()")->fetch()['sum'];
$totalProducts = $db->query("SELECT COUNT(*) as count FROM products")->fetch()['count'];
$newOrders = $db->query("SELECT COUNT(*) as count FROM orders WHERE status = 'new'")->fetch()['count'];

// Отзывы на модерации (защищаемся от ошибки, если таблица ещё не создана)
$pendingReviews = 0;
try {
    $pendingReviews = (int)$db->query("SELECT COUNT(*) as count FROM reviews WHERE status = 'pending'")->fetch()['count'];
} catch (Exception $e) {
    // Таблица reviews ещё не создана — миграция не выполнена
}

// Последние заказы
$stmt = $db->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 10");
$recentOrders = $stmt->fetchAll();

include __DIR__ . '/../templates/header.php';
?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="fw-bold">Дашборд</h1>
        <a href="<?= SITE_URL ?>/admin/logout.php" class="btn btn-outline-danger">
            <i class="fas fa-sign-out-alt me-2"></i>Выйти
        </a>
    </div>
    
    <!-- Карточки статистики -->
    <div class="row g-4 mb-5">
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
                <i class="fas fa-shopping-bag fa-2x text-primary mb-3"></i>
                <h3 class="fw-bold mb-1"><?= $todayOrders ?></h3>
                <p class="text-muted mb-0">Заказов сегодня</p>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
                <i class="fas fa-ruble-sign fa-2x text-success mb-3"></i>
                <h3 class="fw-bold mb-1"><?= formatPrice($todayRevenue) ?></h3>
                <p class="text-muted mb-0">Выручка сегодня</p>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
                <i class="fas fa-box fa-2x text-info mb-3"></i>
                <h3 class="fw-bold mb-1"><?= $totalProducts ?></h3>
                <p class="text-muted mb-0">Всего товаров</p>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center position-relative">
                <i class="fas fa-clock fa-2x text-warning mb-3"></i>
                <h3 class="fw-bold mb-1"><?= $newOrders ?></h3>
                <p class="text-muted mb-0">Новых заказов</p>
            </div>
        </div>
    </div>
    
    <!-- Быстрые действия -->
    <div class="row g-4 mb-5">
        <div class="col-md-6 col-lg-3">
            <a href="<?= SITE_URL ?>/admin/products.php" class="card border-0 shadow-sm rounded-4 p-4 text-decoration-none h-100">
                <div class="d-flex align-items-center">
                    <i class="fas fa-box fa-2x text-primary me-3"></i>
                    <div>
                        <h5 class="fw-bold mb-1">Товары</h5>
                        <p class="text-muted mb-0 small">Управление товарами</p>
                    </div>
                </div>
            </a>
        </div>
        <!-- Email-уведомления -->
<div class="col-md-6 col-lg-4">
    <a href="<?= SITE_URL ?>/admin/email_log.php" class="text-decoration-none">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 admin-card">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="admin-card-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="fas fa-envelope"></i>
                </div>
                <?php
                // Бейдж количества ошибок отправки за последние 24 часа
                $errStmt = $db->query("SELECT COUNT(*) FROM email_log WHERE status = 'failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                $errCount = (int)$errStmt->fetchColumn();
                if ($errCount > 0):
                ?>
                    <span class="badge bg-danger"><?= $errCount ?> ошибок</span>
                <?php endif; ?>
            </div>
            <h5 class="fw-bold mb-2">Email-уведомления</h5>
            <p class="text-muted small mb-0">История отправленных писем клиентам</p>
        </div>
    </a>
</div>
        
        <div class="col-md-6 col-lg-3">
            <a href="<?= SITE_URL ?>/admin/orders.php" class="card border-0 shadow-sm rounded-4 p-4 text-decoration-none h-100">
                <div class="d-flex align-items-center">
                    <i class="fas fa-list fa-2x text-success me-3"></i>
                    <div>
                        <h5 class="fw-bold mb-1">Заказы</h5>
                        <p class="text-muted mb-0 small">Управление заказами</p>
                    </div>
                </div>
            </a>
        </div>
        
        <div class="col-md-6 col-lg-3">
            <a href="<?= SITE_URL ?>/admin/categories.php" class="card border-0 shadow-sm rounded-4 p-4 text-decoration-none h-100">
                <div class="d-flex align-items-center">
                    <i class="fas fa-tags fa-2x text-info me-3"></i>
                    <div>
                        <h5 class="fw-bold mb-1">Категории</h5>
                        <p class="text-muted mb-0 small">Управление категориями</p>
                    </div>
                </div>
            </a>
        </div>
        
        <!-- НОВОЕ: Отзывы -->
        <div class="col-md-6 col-lg-3">
            <a href="<?= SITE_URL ?>/admin/reviews.php" class="card border-0 shadow-sm rounded-4 p-4 text-decoration-none h-100 position-relative">
                <div class="d-flex align-items-center">
                    <i class="fas fa-star fa-2x text-warning me-3"></i>
                    <div>
                        <h5 class="fw-bold mb-1">
                            Отзывы
                            <?php if ($pendingReviews > 0): ?>
                                <span class="badge bg-danger ms-1"><?= $pendingReviews ?></span>
                            <?php endif; ?>
                        </h5>
                        <p class="text-muted mb-0 small">
                            <?php if ($pendingReviews > 0): ?>
                                Ожидают модерации
                            <?php else: ?>
                                Модерация отзывов
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </a>
        </div>
    </div>
    
    <!-- Последние заказы -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0 py-3">
            <h4 class="fw-bold mb-0">Последние заказы</h4>
        </div>
        <div class="card-body p-0">
            <?php if (empty($recentOrders)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Заказов пока нет</p>
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
                                <th>Сумма</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td><strong>#<?= $order['id'] ?></strong></td>
                                    <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                                    <td><?= escape($order['customer_name']) ?></td>
                                    <td><?= escape($order['phone']) ?></td>
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
                                        <a href="<?= SITE_URL ?>/admin/orders.php?view=<?= $order['id'] ?>" 
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

<?php include __DIR__ . '/../templates/footer.php'; ?>