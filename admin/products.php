<?php
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Товары - Админ-панель';
$db = getDB();
$message = '';
$error = '';

// Обработка удаления
if (isset($_POST['delete_id'])) {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности';
    } else {
        $deleteId = intval($_POST['delete_id']);
        $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$deleteId]);
        $message = 'Товар успешно удален';
    }
}

// Обработка добавления/редактирования
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности';
    } else {
        $name = trim($_POST['name'] ?? '');
        $categoryId = intval($_POST['category_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $image = trim($_POST['image'] ?? '');
        $isPopular = isset($_POST['is_popular']) ? 1 : 0;
        $manufacturer = trim($_POST['manufacturer'] ?? '');
        $composition = trim($_POST['composition'] ?? '');
        $calories = $_POST['calories'] !== '' ? floatval($_POST['calories']) : null;
        $proteins = $_POST['proteins'] !== '' ? floatval($_POST['proteins']) : null;
        $fats = $_POST['fats'] !== '' ? floatval($_POST['fats']) : null;
        $carbohydrates = $_POST['carbohydrates'] !== '' ? floatval($_POST['carbohydrates']) : null;
        
        if (empty($name) || $categoryId <= 0 || $price <= 0) {
            $error = 'Заполните все обязательные поля';
        } else {
            $slug = generateSlug($name);
            
            if ($_POST['action'] === 'add') {
                // Добавление
                $stmt = $db->prepare("INSERT INTO products (category_id, name, slug, description, price, image, is_popular, manufacturer, composition, calories, proteins, fats, carbohydrates) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$categoryId, $name, $slug, $description, $price, $image, $isPopular, $manufacturer, $composition, $calories, $proteins, $fats, $carbohydrates]);
                $message = 'Товар успешно добавлен';
            } elseif ($_POST['action'] === 'edit') {
                // Редактирование
                $id = intval($_POST['id'] ?? 0);
                $stmt = $db->prepare("UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, image = ?, is_popular = ?, manufacturer = ?, composition = ?, calories = ?, proteins = ?, fats = ?, carbohydrates = ? WHERE id = ?");
                $stmt->execute([$categoryId, $name, $description, $price, $image, $isPopular, $manufacturer, $composition, $calories, $proteins, $fats, $carbohydrates, $id]);
                $message = 'Товар успешно обновлен';
            }
        }
    
    }
// После успешного UPDATE products SET ...
setFlashMessage('Товар успешно обновлён', 'success');

// Собираем URL возврата из POST'a
$returnParams = [];
if (!empty($_POST['return_page']))     $returnParams['page']     = (int)$_POST['return_page'];
if (!empty($_POST['return_category'])) $returnParams['category'] = (int)$_POST['return_category'];
if (!empty($_POST['return_search']))   $returnParams['search']   = trim($_POST['return_search']);

$redirectUrl = '/admin/products.php' 
    . (!empty($returnParams) ? '?' . http_build_query($returnParams) : '');

redirect($redirectUrl);
exit;    
}

// Режим редактирования
$editProduct = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$editId]);
    $editProduct = $stmt->fetch();
}

// Получаем категории для select
$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Список товаров (если не редактируем)
$products = [];
if (!$editProduct && !isset($_GET['add'])) {
    // Фильтр по категории
    $categoryFilter = isset($_GET['category']) ? intval($_GET['category']) : 0;
    
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $itemsPerPage = 15;
    $offset = ($page - 1) * $itemsPerPage;
    
    // Строим запрос с фильтром
    $sql = "SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE 1=1";
    $params = [];
    
    if ($categoryFilter > 0) {
        $sql .= " AND p.category_id = ?";
        $params[] = $categoryFilter;
    }
    
    $sql .= " ORDER BY p.created_at DESC";
    
    // Получаем общее количество
    $countSql = "SELECT COUNT(*) as count FROM products WHERE 1=1";
    $countParams = [];
    
    if ($categoryFilter > 0) {
        $countSql .= " AND category_id = ?";
        $countParams[] = $categoryFilter;
    }
    
    $totalItems = $db->prepare($countSql);
    $totalItems->execute($countParams);
    $totalItems = $totalItems->fetch()['count'];
    $totalPages = ceil($totalItems / $itemsPerPage);
    
    // Пагинация
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $itemsPerPage;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
}

// ============================================
// ВОЗВРАТ К СПИСКУ — сохраняем фильтры/страницу
// ============================================
// Параметры списка, которые надо сохранить при переходе в форму редактирования
$listParams = [];
if (!empty($_GET['page']))     $listParams['page']     = (int)$_GET['page'];
if (!empty($_GET['category'])) $listParams['category'] = (int)$_GET['category'];
if (!empty($_GET['search']))   $listParams['search']   = trim($_GET['search']);

// URL для кнопки «Назад к списку»
$backToListUrl = SITE_URL . '/admin/products.php' 
    . (!empty($listParams) ? '?' . http_build_query($listParams) : '');

include __DIR__ . '/../templates/header.php';
?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="fw-bold">Управление товарами</h1>
        <div>
            <?php if ($editProduct || isset($_GET['add'])): ?>
                <a href="<?= escape($backToListUrl) ?>" class="btn btn-outline-secondary">
    <i class="fas fa-arrow-left me-2"></i>Назад к списку
</a>
            <?php else: ?>
                <a href="<?= SITE_URL ?>/admin/products.php?add=1" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Добавить товар
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Фильтр по категориям -->
    <?php if (!$editProduct && !isset($_GET['add'])): ?>
        <div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-6 col-lg-4">
                    <label class="form-label fw-semibold">Фильтр по категории</label>
                    <select class="form-select" name="category" onchange="this.form.submit()">
                        <option value="0">Все категории</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($categoryFilter ?? 0) == $cat['id'] ? 'selected' : '' ?>>
                                <?= escape($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 col-lg-8">
                    <?php if (!empty($categoryFilter) && $categoryFilter > 0): ?>
                        <a href="<?= SITE_URL ?>/admin/products.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Сбросить фильтр
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?= escape($message) ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= escape($error) ?></div>
    <?php endif; ?>
    
    <!-- Форма добавления/редактирования -->
    <?php if ($editProduct || isset($_GET['add'])): ?>
        <div class="card border-0 shadow-sm rounded-4 p-4">
            <h3 class="fw-bold mb-4"><?= $editProduct ? 'Редактирование товара' : 'Добавление товара' ?></h3>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="<?= $editProduct ? 'edit' : 'add' ?>">
                <?php if ($editProduct): ?>
                    <input type="hidden" name="id" value="<?= $editProduct['id'] ?>">
                <?php endif; ?>
                
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Название <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               name="name" 
                               required 
                               value="<?= escape($editProduct['name'] ?? '') ?>">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Категория <span class="text-danger">*</span></label>
                        <select class="form-select" name="category_id" required>
                            <option value="">Выберите категорию</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($editProduct['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                    <?= escape($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Описание</label>
                        <textarea class="form-control" name="description" rows="3"><?= escape($editProduct['description'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Цена (₽) <span class="text-danger">*</span></label>
                        <input type="number" 
                               class="form-control" 
                               name="price" 
                               step="0.01" 
                               min="0" 
                               required
                               value="<?= $editProduct['price'] ?? '' ?>">
                    </div>
                    
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">URL изображения</label>
                        <input type="url" 
                               class="form-control" 
                               name="image" 
                               placeholder="https://example.com/image.jpg"
                               value="<?= escape($editProduct['image'] ?? '') ?>">
                    </div>
                    
                    <div class="col-md-12">
                        <div class="form-check">
                            <input class="form-check-input" 
                                   type="checkbox" 
                                   name="is_popular" 
                                   id="is_popular"
                                   <?= ($editProduct['is_popular'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_popular">
                                Популярный товар
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-12"><hr class="my-4"></div>
                    
                    <div class="col-12">
                        <h5 class="fw-bold mb-3"><i class="fas fa-info-circle me-2 text-primary"></i>Дополнительная информация</h5>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Производитель</label>
                        <input type="text" 
                               class="form-control" 
                               name="manufacturer"
                               placeholder="Название производителя"
                               value="<?= escape($editProduct['manufacturer'] ?? '') ?>">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Состав товара</label>
                        <input type="text" 
                               class="form-control" 
                               name="composition"
                               placeholder="Перечислите состав через запятую"
                               value="<?= escape($editProduct['composition'] ?? '') ?>">
                    </div>
                    
                    <div class="col-12"><hr class="my-3"></div>
                    
                    <div class="col-12">
                        <h6 class="fw-bold mb-3"><i class="fas fa-chart-pie me-2 text-primary"></i>Пищевая ценность (на 100г)</h6>
                        <small class="text-muted">Оставьте пустым, если не применяется (например, для бытовой химии)</small>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Калории (ккал)</label>
                        <input type="number" 
                               class="form-control" 
                               name="calories"
                               step="0.01"
                               min="0"
                               placeholder="0.00"
                               value="<?= $editProduct['calories'] ?? '' ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Белки (г)</label>
                        <input type="number" 
                               class="form-control" 
                               name="proteins"
                               step="0.01"
                               min="0"
                               placeholder="0.00"
                               value="<?= $editProduct['proteins'] ?? '' ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Жиры (г)</label>
                        <input type="number" 
                               class="form-control" 
                               name="fats"
                               step="0.01"
                               min="0"
                               placeholder="0.00"
                               value="<?= $editProduct['fats'] ?? '' ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Углеводы (г)</label>
                        <input type="number" 
                               class="form-control" 
                               name="carbohydrates"
                               step="0.01"
                               min="0"
                               placeholder="0.00"
                               value="<?= $editProduct['carbohydrates'] ?? '' ?>">
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i><?= $editProduct ? 'Сохранить' : 'Добавить' ?>
                    </button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <!-- Список товаров -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <?php if (empty($products)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Товаров пока нет</p>
                        <a href="<?= SITE_URL ?>/admin/products.php?add=1" class="btn btn-primary mt-3">
                            Добавить первый товар
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Изображение</th>
                                    <th>Название</th>
                                    <th>Категория</th>
                                    <th>Цена</th>
                                    <th>Популярный</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><strong><?= $product['id'] ?></strong></td>
                                        <td>
                                            <img src="<?= escape($product['image'] ?: '/assets/images/placeholder.jpg') ?>" 
                                                 alt="" 
                                                 style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                                        </td>
                                        <td><?= escape($product['name']) ?></td>
                                        <td><?= escape($product['category_name']) ?></td>
                                        <td><strong><?= formatPrice($product['price']) ?></strong></td>
                                        <td>
                                            <?= $product['is_popular'] ? '<span class="badge bg-success">Да</span>' : '<span class="badge bg-secondary">Нет</span>' ?>
                                        </td>
                                        <td>
                                            <?php
$editParams = array_merge(['edit' => $product['id']], $listParams);
$editUrl = SITE_URL . '/admin/products.php?' . http_build_query($editParams);
?>
<a href="<?= escape($editUrl) ?>" class="btn btn-sm btn-outline-primary">
    <i class="fas fa-edit"></i>
</a>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Удалить товар?');">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="delete_id" value="<?= $product['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <!-- Сохранение контекста списка для возврата -->
<?php if (!empty($listParams['page'])): ?>
    <input type="hidden" name="return_page" value="<?= (int)$listParams['page'] ?>">
<?php endif; ?>
<?php if (!empty($listParams['category'])): ?>
    <input type="hidden" name="return_category" value="<?= (int)$listParams['category'] ?>">
<?php endif; ?>
<?php if (!empty($listParams['search'])): ?>
    <input type="hidden" name="return_search" value="<?= escape($listParams['search']) ?>">
<?php endif; ?>
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Пагинация -->
                    <?php if ($totalPages > 1): ?>
                        <nav class="p-3">
                            <ul class="pagination justify-content-center mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>">
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
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
