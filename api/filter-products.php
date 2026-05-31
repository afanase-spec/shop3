<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Проверка метода
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Неверный метод']);
    exit;
}

$db = getDB();

// Параметры фильтрации
$categoryId = isset($_GET['category']) ? intval($_GET['category']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$itemsPerPage = 9;
$offset = ($page - 1) * $itemsPerPage;

// Строим запрос с фильтрами
$sql = "SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE 1=1";
$params = [];

if ($categoryId > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $categoryId;
}

if (!empty($search)) {
    $sql .= " AND p.name LIKE ?";
    $params[] = "%$search%";
}

// Получаем общее количество товаров
$countSql = "SELECT COUNT(*) as total FROM products p JOIN categories c ON p.category_id = c.id WHERE 1=1";
$countParams = [];

if ($categoryId > 0) {
    $countSql .= " AND p.category_id = ?";
    $countParams[] = $categoryId;
}

if (!empty($search)) {
    $countSql .= " AND p.name LIKE ?";
    $countParams[] = "%$search%";
}

$stmt = $db->prepare($countSql);
$stmt->execute($countParams);
$totalItems = $stmt->fetch()['total'];
$totalPages = ceil($totalItems / $itemsPerPage);

// Получаем товары
$sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
$params[] = $itemsPerPage;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Генерируем HTML
ob_start();

if (empty($products)) {
    ?>
    <div class="text-center py-5">
        <i class="fas fa-search fa-3x text-muted mb-3"></i>
        <h4 class="text-muted">Товары не найдены</h4>
        <p class="text-muted">Попробуйте изменить параметры поиска</p>
    </div>
    <?php
} else {
    ?>
    <div class="row g-4">
        <?php foreach ($products as $product): ?>
            <div class="col-md-6 col-xl-4">
                <div class="product-card animate-fade-in">
                    <a href="<?= SITE_URL ?>/product.php?id=<?= $product['id'] ?>">
                        <img src="<?= escape($product['image'] ?: '/assets/images/placeholder.jpg') ?>" 
                             alt="<?= escape($product['name']) ?>">
                    </a>
                    <div class="card-body">
                        <h5 class="card-title">
                            <a href="<?= SITE_URL ?>/product.php?id=<?= $product['id'] ?>">
                                <?= escape($product['name']) ?>
                            </a>
                        </h5>
                        <p class="text-muted small mb-2"><?= escape($product['category_name']) ?></p>
                        <div class="price"><?= formatPrice($product['price']) ?></div>
                        <button class="btn btn-primary w-100 add-to-cart-btn" 
                                data-product-id="<?= $product['id'] ?>"
                                data-product-name="<?= escape($product['name']) ?>">
                            <i class="fas fa-cart-plus me-2"></i>В корзину
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Пагинация -->
    <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="#" onclick="loadPage(<?= $page - 1 ?>); return false;">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="#" onclick="loadPage(<?= $i ?>); return false;">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="#" onclick="loadPage(<?= $page + 1 ?>); return false;">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
    <?php
}

$html = ob_get_clean();

echo json_encode([
    'success' => true,
    'html' => $html,
    'totalPages' => $totalPages,
    'currentPage' => $page
]);
