<?php
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Email-уведомления - Админ-панель';
$db = getDB();

// Фильтры
$statusFilter = $_GET['status'] ?? '';
$eventFilter  = $_GET['event'] ?? '';
$searchEmail  = trim($_GET['email'] ?? '');

$sql = "SELECT * FROM email_log WHERE 1=1";
$params = [];

if (!empty($statusFilter) && in_array($statusFilter, ['sent', 'failed', 'logged'])) {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
}

if (!empty($eventFilter)) {
    $sql .= " AND event_type = ?";
    $params[] = $eventFilter;
}

if (!empty($searchEmail)) {
    $sql .= " AND recipient LIKE ?";
    $params[] = '%' . $searchEmail . '%';
}

$sql .= " ORDER BY created_at DESC LIMIT 200";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Статистика
$stats = $db->query("
    SELECT 
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent_count,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_count,
        SUM(CASE WHEN status = 'logged' THEN 1 ELSE 0 END) AS logged_count,
        COUNT(*) AS total_count
    FROM email_log
")->fetch();

include __DIR__ . '/../templates/header.php';
?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">
            <i class="fas fa-envelope me-2 text-primary"></i>
            Email-уведомления
        </h1>
        <a href="<?= SITE_URL ?>/admin/index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>В админку
        </a>
    </div>
    
    <!-- Статистика -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 text-center">
                <div class="text-muted small">Всего отправлено</div>
                <div class="fs-2 fw-bold text-primary"><?= (int)$stats['total_count'] ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 text-center">
                <div class="text-muted small">✅ Успешно</div>
                <div class="fs-2 fw-bold text-success"><?= (int)$stats['sent_count'] ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 text-center">
                <div class="text-muted small">📝 В лог-файл</div>
                <div class="fs-2 fw-bold text-info"><?= (int)$stats['logged_count'] ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 text-center">
                <div class="text-muted small">❌ Ошибки</div>
                <div class="fs-2 fw-bold text-danger"><?= (int)$stats['failed_count'] ?></div>
            </div>
        </div>
    </div>
    
    <!-- Фильтры -->
    <div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Статус</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Все</option>
                    <option value="sent" <?= $statusFilter === 'sent' ? 'selected' : '' ?>>Отправлено</option>
                    <option value="logged" <?= $statusFilter === 'logged' ? 'selected' : '' ?>>В лог-файл</option>
                    <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Ошибка</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Событие</label>
                <select name="event" class="form-select form-select-sm">
                    <option value="">Все</option>
                    <option value="order_created" <?= $eventFilter === 'order_created' ? 'selected' : '' ?>>Заказ создан</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">Email получателя</label>
                <input type="text" name="email" value="<?= escape($searchEmail) ?>" 
                       placeholder="поиск по email..." class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="fas fa-filter me-1"></i>Применить
                </button>
            </div>
        </form>
    </div>
    
    <!-- Таблица -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <?php if (empty($logs)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Пока нет ни одной отправки</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>ID</th>
                                <th>Дата/время</th>
                                <th>Получатель</th>
                                <th>Тема</th>
                                <th>Заказ</th>
                                <th>Статус</th>
                                <th>Канал</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><span class="text-muted">#<?= $log['id'] ?></span></td>
                                    <td>
                                        <small><?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?></small>
                                    </td>
                                    <td><strong><?= escape($log['recipient']) ?></strong></td>
                                    <td>
                                        <small><?= escape(mb_substr($log['subject'], 0, 60)) ?>
                                        <?= mb_strlen($log['subject']) > 60 ? '…' : '' ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($log['order_id'])): ?>
                                            <a href="<?= SITE_URL ?>/admin/orders.php?view=<?= (int)$log['order_id'] ?>" 
                                               class="badge bg-light text-dark text-decoration-none">
                                                #<?= (int)$log['order_id'] ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusBadges = [
                                            'sent'   => ['bg-success', '✅ Отправлено'],
                                            'logged' => ['bg-info', '📝 В лог'],
                                            'failed' => ['bg-danger', '❌ Ошибка']
                                        ];
                                        $badge = $statusBadges[$log['status']] ?? ['bg-secondary', $log['status']];
                                        ?>
                                        <span class="badge <?= $badge[0] ?>" 
                                              <?= !empty($log['error_message']) ? 'title="' . escape($log['error_message']) . '"' : '' ?>>
                                            <?= $badge[1] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= $log['transport'] === 'smtp' ? '📧 SMTP' : '📁 Файл' ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php if (!empty($log['error_message'])): ?>
                                <tr>
                                    <td colspan="7" class="bg-light">
                                        <small class="text-danger">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            <?= escape($log['error_message']) ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="p-3 text-muted small">
                    Показано <?= count($logs) ?> записей (последние 200)
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>