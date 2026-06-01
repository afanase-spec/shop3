<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Каталог - ' . SITE_NAME;
$db = getDB();

// Параметры фильтрации
$categoryId = isset($_GET['category']) ? intval($_GET['category']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$itemsPerPage = 9;
$offset = ($page - 1) * $itemsPerPage;

// Получаем категории для фильтра
$stmt = $db->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

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

// === РЕЙТИНГИ для всех товаров одним запросом ===
$productIds = array_column($products, 'id');
$ratings = getRatingsForProducts($productIds);

include __DIR__ . '/templates/header.php';
?>

<?php $breadcrumbs = [['Каталог', null]]; include __DIR__ . '/templates/breadcrumbs.php'; ?>

<div class="container my-5">
    <h1 class="section-title">Каталог товаров</h1>
    
    <!-- Поиск и фильтры вверху -->
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-6 col-lg-4">
                <label class="form-label fw-semibold"><i class="fas fa-search me-2 text-primary"></i>Поиск товаров</label>
                <input type="text" 
                       class="form-control" 
                       id="searchInput" 
                       placeholder="Введите название товара..."
                       value="<?= escape($search) ?>">
            </div>
            
            <div class="col-md-6 col-lg-3">
                <label class="form-label fw-semibold"><i class="fas fa-tags me-2 text-primary"></i>Категория</label>
                <select class="form-select" id="categoryFilter">
                    <option value="0">Все категории</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>>
                            <?= escape($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-12 col-lg-5">
                <button class="btn btn-primary w-100" onclick="applyFilters()">
                    <i class="fas fa-filter me-2"></i>Применить фильтры
                </button>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        <!-- Список товаров -->
        <div class="col-lg-9">
            <div id="productsContainer">
                <?php if (empty($products)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">Товары не найдены</h4>
                        <p class="text-muted">Попробуйте изменить параметры поиска</p>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($products as $product): ?>
                            <?php
                            $productRating = $ratings[(int)$product['id']] ?? null;
                            ?>
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
                                        
                                        <!-- РЕЙТИНГ НА КАРТОЧКЕ -->
                                        <div class="product-card-rating mb-2">
                                            <?php if ($productRating !== null): ?>
                                                <?= renderStars($productRating['average'], 'sm') ?>
                                                <span class="ms-1 small text-muted">
                                                    <?= number_format($productRating['average'], 1, '.', '') ?>
                                                    (<?= $productRating['count'] ?>)
                                                </span>
                                            <?php else: ?>
                                                <span class="small text-muted">
                                                    <i class="far fa-star me-1"></i>Нет отзывов
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <button class="btn add-to-cart-btn" 
                                                data-product-id="<?= $product['id'] ?>"
                                                data-product-name="<?= escape($product['name']) ?>">
                                            <span><?= formatPrice($product['price']) ?></span>
                                            <i class="fas fa-plus"></i>
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
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function applyFilters() {
    const category = document.getElementById('categoryFilter').value;
    const search = document.getElementById('searchInput').value;
    
    const params = new URLSearchParams();
    if (category && category != '0') params.set('category', category);
    if (search) params.set('search', search);
    params.set('page', '1');
    
    window.location.href = '?' + params.toString();
}

// Поиск при нажатии Enter
document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        applyFilters();
    }
});
</script>

<?php include __DIR__ . '/templates/footer.php'; ?>