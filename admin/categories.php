<?php
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Категории - Админ-панель';
$db = getDB();
$message = '';
$error = '';

// Обработка удаления
if (isset($_POST['delete_id'])) {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности';
    } else {
        $deleteId = intval($_POST['delete_id']);
        
        // Проверяем, есть ли товары в категории
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
        $stmt->execute([$deleteId]);
        $productCount = $stmt->fetch()['count'];
        
        if ($productCount > 0) {
            $error = "Нельзя удалить категорию, в которой есть товары ($productCount шт.)";
        } else {
            $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$deleteId]);
            $message = 'Категория успешно удалена';
        }
    }
}

// Обработка добавления/редактирования
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности';
    } else {
        $name = trim($_POST['name'] ?? '');
        $slug = generateSlug($_POST['slug'] ?? $name);
        
        if (empty($name)) {
            $error = 'Введите название категории';
        } else {
            if ($_POST['action'] === 'add') {
                $stmt = $db->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
                $stmt->execute([$name, $slug]);
                $message = 'Категория успешно добавлена';
            } elseif ($_POST['action'] === 'edit') {
                $id = intval($_POST['id'] ?? 0);
                $stmt = $db->prepare("UPDATE categories SET name = ?, slug = ? WHERE id = ?");
                $stmt->execute([$name, $slug, $id]);
                $message = 'Категория успешно обновлена';
            }
        }
    }
}

// Режим редактирования
$editCategory = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$editId]);
    $editCategory = $stmt->fetch();
}

// Список категорий (если не редактируем)
$categories = [];
if (!$editCategory && !isset($_GET['add'])) {
    $stmt = $db->query("SELECT c.*, COUNT(p.id) as product_count 
                        FROM categories c 
                        LEFT JOIN products p ON c.id = p.category_id 
                        GROUP BY c.id 
                        ORDER BY c.name");
    $categories = $stmt->fetchAll();
}

include __DIR__ . '/../templates/header.php';
?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="fw-bold">Управление категориями</h1>
        <div>
            <?php if ($editCategory || isset($_GET['add'])): ?>
                <a href="<?= SITE_URL ?>/admin/categories.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Назад к списку
                </a>
            <?php else: ?>
                <a href="<?= SITE_URL ?>/admin/categories.php?add=1" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Добавить категорию
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?= escape($message) ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= escape($error) ?></div>
    <?php endif; ?>
    
    <!-- Форма добавления/редактирования -->
    <?php if ($editCategory || isset($_GET['add'])): ?>
        <div class="card border-0 shadow-sm rounded-4 p-4">
            <h3 class="fw-bold mb-4"><?= $editCategory ? 'Редактирование категории' : 'Добавление категории' ?></h3>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="<?= $editCategory ? 'edit' : 'add' ?>">
                <?php if ($editCategory): ?>
                    <input type="hidden" name="id" value="<?= $editCategory['id'] ?>">
                <?php endif; ?>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Название <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               name="name" 
                               required 
                               value="<?= escape($editCategory['name'] ?? '') ?>"
                               oninput="document.getElementById('slug').value = generateSlug(this.value)">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Slug</label>
                        <input type="text" 
                               class="form-control" 
                               name="slug" 
                               id="slug"
                               value="<?= escape($editCategory['slug'] ?? '') ?>">
                        <small class="text-muted">Используется для URL (заполняется автоматически)</small>
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i><?= $editCategory ? 'Сохранить' : 'Добавить' ?>
                    </button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <!-- Список категорий -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <?php if (empty($categories)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Категорий пока нет</p>
                        <a href="<?= SITE_URL ?>/admin/categories.php?add=1" class="btn btn-primary mt-3">
                            Добавить первую категорию
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Название</th>
                                    <th>Slug</th>
                                    <th>Товаров</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td><strong><?= $category['id'] ?></strong></td>
                                        <td><?= escape($category['name']) ?></td>
                                        <td><code><?= escape($category['slug']) ?></code></td>
                                        <td>
                                            <span class="badge bg-info"><?= $category['product_count'] ?></span>
                                        </td>
                                        <td>
                                            <a href="?edit=<?= $category['id'] ?>" class="btn btn-sm btn-outline-primary me-1">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Удалить категорию?');">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="delete_id" value="<?= $category['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
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

<script>
function generateSlug(text) {
    return text.toLowerCase()
        .replace(/[^\w\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .trim();
}
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
