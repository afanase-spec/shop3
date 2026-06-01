<?php
require_once __DIR__ . '/../includes/auth.php';

$db = getDB();

// === ОБРАБОТКА ДЕЙСТВИЙ ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Ошибка безопасности');
        redirect('/admin/reviews.php');
    }
    
    $action = $_POST['action'] ?? '';
    $reviewId = (int)($_POST['review_id'] ?? 0);
    
    if ($reviewId > 0) {
        if ($action === 'approve') {
            $stmt = $db->prepare("UPDATE reviews SET status = 'approved', moderated_at = NOW() WHERE id = ?");
            $stmt->execute([$reviewId]);
            setFlashMessage('success', 'Отзыв опубликован');
        } elseif ($action === 'hide') {
            $stmt = $db->prepare("UPDATE reviews SET status = 'hidden', moderated_at = NOW() WHERE id = ?");
            $stmt->execute([$reviewId]);
            setFlashMessage('success', 'Отзыв скрыт (но учитывается в рейтинге)');
        } elseif ($action === 'delete') {
            $stmt = $db->prepare("DELETE FROM reviews WHERE id = ?");
            $stmt->execute([$reviewId]);
            setFlashMessage('success', 'Отзыв удалён');
        } elseif ($action === 'restore_pending') {
            $stmt = $db->prepare("UPDATE reviews SET status = 'pending', moderated_at = NULL WHERE id = ?");
            $stmt->execute([$reviewId]);
            setFlashMessage('success', 'Отзыв возвращён на модерацию');
        }
    }
    
    redirect('/admin/reviews.php' . (!empty($_POST['return_filter']) ? '?status=' . urlencode($_POST['return_filter']) : ''));
}

// === ФИЛЬТР ПО СТАТУСУ ===
$filter = $_GET['status'] ?? 'pending';
$allowedFilters = ['pending', 'approved', 'hidden', 'all'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'pending';
}

// === СЧЁТЧИКИ ===
$counts = $db->query("
    SELECT status, COUNT(*) AS cnt 
    FROM reviews 
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

$pendingCount = (int)($counts['pending'] ?? 0);
$approvedCount = (int)($counts['approved'] ?? 0);
$hiddenCount = (int)($counts['hidden'] ?? 0);
$totalCount = $pendingCount + $approvedCount + $hiddenCount;

// === ВЫБОРКА ОТЗЫВОВ ===
$where = '';
$bindParams = [];
if ($filter !== 'all') {
    $where = 'WHERE r.status = ?';
    $bindParams[] = $filter;
}

$sql = "
    SELECT r.id, r.rating, r.comment, r.status, r.created_at, r.moderated_at,
           p.id AS product_id, p.name AS product_name, p.image AS product_image,
           u.id AS user_id, u.name AS user_name, u.email AS user_email
    FROM reviews r
    JOIN products p ON r.product_id = p.id
    JOIN users u ON r.user_id = u.id
    $where
    ORDER BY 
        CASE r.status 
            WHEN 'pending' THEN 1 
            WHEN 'approved' THEN 2 
            WHEN 'hidden' THEN 3 
        END,
        r.created_at DESC
";
$stmt = $db->prepare($sql);
$stmt->execute($bindParams);
$reviews = $stmt->fetchAll();

$pageTitle = 'Модерация отзывов - Админ';
include __DIR__ . '/../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h3 mb-0"><i class="fas fa-star me-2 text-warning"></i>Модерация отзывов</h1>
        
        <?php if ($pendingCount > 0): ?>
            <span class="badge bg-warning text-dark fs-6 px-3 py-2">
                <i class="fas fa-clock me-1"></i>
                Ожидает модерации: <?= $pendingCount ?>
            </span>
        <?php endif; ?>
    </div>
    
    <!-- Flash сообщения -->
    <?php $flash = getFlashMessage(); if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= escape($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Табы фильтров -->
    <ul class="nav nav-pills mb-4 review-filter-tabs">
        <li class="nav-item">
            <a class="nav-link <?= $filter === 'pending' ? 'active' : '' ?>" href="?status=pending">
                <i class="fas fa-clock me-1"></i>
                Ожидают
                <?php if ($pendingCount > 0): ?>
                    <span class="badge bg-danger ms-1"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $filter === 'approved' ? 'active' : '' ?>" href="?status=approved">
                <i class="fas fa-check-circle me-1"></i>
                Опубликованные
                <span class="badge bg-secondary ms-1"><?= $approvedCount ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $filter === 'hidden' ? 'active' : '' ?>" href="?status=hidden">
                <i class="fas fa-eye-slash me-1"></i>
                Скрытые
                <span class="badge bg-secondary ms-1"><?= $hiddenCount ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $filter === 'all' ? 'active' : '' ?>" href="?status=all">
                <i class="fas fa-list me-1"></i>
                Все
                <span class="badge bg-secondary ms-1"><?= $totalCount ?></span>
            </a>
        </li>
    </ul>
    
    <!-- Список отзывов -->
    <?php if (empty($reviews)): ?>
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
            <i class="far fa-comment-dots fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">Отзывов нет</h5>
            <p class="text-muted mb-0">
                <?php if ($filter === 'pending'): ?>
                    Все отзывы промодерированы 🎉
                <?php else: ?>
                    В этой категории отзывов пока нет
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($reviews as $rev): ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm rounded-4 admin-review-card status-<?= escape($rev['status']) ?>">
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <!-- Инфо о товаре -->
                                <div class="col-md-3 col-lg-2">
                                    <div class="d-flex flex-column align-items-center text-center">
                                        <img src="<?= escape($rev['product_image'] ?: '/assets/images/placeholder.jpg') ?>" 
                                             alt="<?= escape($rev['product_name']) ?>"
                                             class="rounded mb-2"
                                             style="width: 80px; height: 80px; object-fit: cover;">
                                        <a href="<?= SITE_URL ?>/product.php?id=<?= $rev['product_id'] ?>" 
                                           target="_blank"
                                           class="small fw-semibold text-decoration-none">
                                            <?= escape($rev['product_name']) ?>
                                        </a>
                                    </div>
                                </div>
                                
                                <!-- Содержимое отзыва -->
                                <div class="col-md-6 col-lg-7">
                                    <div class="d-flex align-items-center mb-2 flex-wrap gap-2">
                                        <strong><?= escape($rev['user_name']) ?></strong>
                                        <span class="small text-muted">
                                            <i class="fas fa-envelope me-1"></i><?= escape($rev['user_email']) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <?= renderStars((float)$rev['rating'], 'sm') ?>
                                        <span class="ms-2 fw-bold"><?= (int)$rev['rating'] ?>/5</span>
                                    </div>
                                    
                                    <?php if (!empty($rev['comment'])): ?>
                                        <p class="mb-2"><?= nl2br(escape($rev['comment'])) ?></p>
                                    <?php else: ?>
                                        <p class="text-muted small fst-italic mb-2">(без комментария)</p>
                                    <?php endif; ?>
                                    
                                    <div class="small text-muted">
                                        <i class="far fa-calendar me-1"></i>
                                        Оставлен: <?= date('d.m.Y H:i', strtotime($rev['created_at'])) ?>
                                        <?php if ($rev['moderated_at']): ?>
                                            <span class="ms-2">
                                                <i class="fas fa-gavel me-1"></i>
                                                Модерация: <?= date('d.m.Y H:i', strtotime($rev['moderated_at'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Действия -->
                                <div class="col-md-3 col-lg-3">
                                    <div class="d-flex flex-column gap-2">
                                        <!-- Текущий статус -->
                                        <div class="text-center mb-1">
                                            <?php if ($rev['status'] === 'pending'): ?>
                                                <span class="badge bg-warning text-dark px-3 py-2">
                                                    <i class="fas fa-clock me-1"></i>На модерации
                                                </span>
                                            <?php elseif ($rev['status'] === 'approved'): ?>
                                                <span class="badge bg-success px-3 py-2">
                                                    <i class="fas fa-check me-1"></i>Опубликован
                                                </span>
                                            <?php elseif ($rev['status'] === 'hidden'): ?>
                                                <span class="badge bg-secondary px-3 py-2">
                                                    <i class="fas fa-eye-slash me-1"></i>Скрыт (в рейтинге)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Кнопки действий -->
                                        <?php if ($rev['status'] !== 'approved'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                                <input type="hidden" name="return_filter" value="<?= escape($filter) ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-success btn-sm w-100">
                                                    <i class="fas fa-check me-1"></i>Опубликовать
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($rev['status'] !== 'hidden'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                                <input type="hidden" name="return_filter" value="<?= escape($filter) ?>">
                                                <input type="hidden" name="action" value="hide">
                                                <button type="submit" class="btn btn-warning btn-sm w-100">
                                                    <i class="fas fa-eye-slash me-1"></i>Скрыть
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($rev['status'] !== 'pending'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                                <input type="hidden" name="return_filter" value="<?= escape($filter) ?>">
                                                <input type="hidden" name="action" value="restore_pending">
                                                <button type="submit" class="btn btn-outline-secondary btn-sm w-100">
                                                    <i class="fas fa-undo me-1"></i>На модерацию
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Удалить отзыв навсегда? Это действие нельзя отменить.');">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                            <input type="hidden" name="return_filter" value="<?= escape($filter) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                                <i class="fas fa-trash me-1"></i>Удалить
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>