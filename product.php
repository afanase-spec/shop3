<?php
require_once __DIR__ . '/includes/functions.php';

$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($productId <= 0) {
    redirect('/catalog.php');
}

$db = getDB();

// Получаем товар
$stmt = $db->prepare("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    redirect('/catalog.php');
}

$pageTitle = escape($product['name']) . ' - ' . SITE_NAME;

// Получаем похожие товары (из той же категории)
$stmt = $db->prepare("SELECT * FROM products WHERE category_id = ? AND id != ? LIMIT 4");
$stmt->execute([$product['category_id'], $productId]);
$relatedProducts = $stmt->fetchAll();

include __DIR__ . '/templates/header.php';
?>

<?php
// Хлебные крошки для страницы товара
$breadcrumbs = [
    ['Каталог', '/catalog.php']
];

// Если у товара есть категория — добавляем её
if (!empty($product['category_name']) && !empty($product['category_id'])) {
    $breadcrumbs[] = [$product['category_name'], '/catalog.php?category=' . (int)$product['category_id']];
}

$breadcrumbs[] = [$product['name'], null];

include __DIR__ . '/templates/breadcrumbs.php';
?>

<div class="container my-5 product-detail">
    <!-- Карточка товара -->
    <div class="row g-4 mb-5">
        <div class="col-lg-6">
            <div class="card border-0 rounded-4 overflow-hidden" style="background: transparent;">
                <img src="<?= escape($product['image'] ?: '/assets/images/placeholder.jpg') ?>" 
                     class="card-img-top" 
                     alt="<?= escape($product['name']) ?>"
                     style="height: 500px; object-fit: contain; object-position: center; border-radius: 24px;">
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="ps-lg-4">
                <span class="badge bg-secondary mb-2"><?= escape($product['category_name']) ?></span>
                <?php if ($product['is_popular']): ?>
                    <span class="badge-popular mb-2 ms-2">Популярное</span>
                <?php endif; ?>
                
                <h1 class="fw-bold mt-2"><?= escape($product['name']) ?></h1>
                
                <?php if ($product['manufacturer']): ?>
                    <p class="text-muted mb-2">
                        <i class="fas fa-industry me-2"></i>Производитель: <strong><?= escape($product['manufacturer']) ?></strong>
                    </p>
                <?php endif; ?>
                
                <div class="price display-4 my-3"><?= formatPrice($product['price']) ?></div>
                
                <p class="text-muted mb-4"><?= nl2br(escape($product['description'])) ?></p>
                
                <?php if ($product['composition']): ?>
                    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                        <h5 class="fw-bold mb-3"><i class="fas fa-list-ul me-2 text-primary"></i>Состав</h5>
                        <p class="mb-0"><?= escape($product['composition']) ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($product['calories'] !== null): ?>
                    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                        <h5 class="fw-bold mb-3"><i class="fas fa-chart-pie me-2 text-primary"></i>Пищевая ценность (на 100г)</h5>
                        <div class="nutrition-table">
                            <div class="nutrition-row">
                                <span class="nutrition-label">Калории</span>
                                <span class="nutrition-value"><?= $product['calories'] ?> ккал</span>
                            </div>
                            <div class="nutrition-row">
                                <span class="nutrition-label">Белки</span>
                                <span class="nutrition-value"><?= $product['proteins'] ?> г</span>
                            </div>
                            <div class="nutrition-row">
                                <span class="nutrition-label">Жиры</span>
                                <span class="nutrition-value"><?= $product['fats'] ?> г</span>
                            </div>
                            <div class="nutrition-row">
                                <span class="nutrition-label">Углеводы</span>
                                <span class="nutrition-value"><?= $product['carbohydrates'] ?> г</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <button class="btn add-to-cart-btn w-100" 
                        data-product-id="<?= $product['id'] ?>"
                        data-product-name="<?= escape($product['name']) ?>">
                    <span><?= formatPrice($product['price']) ?></span>
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Похожие товары -->
    <?php if (!empty($relatedProducts)): ?>
        <section class="mt-5">
            <h2 class="section-title">Похожие товары</h2>
            <div class="row g-4">
                <?php foreach ($relatedProducts as $related): ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="product-card animate-fade-in">
                            <a href="<?= SITE_URL ?>/product.php?id=<?= $related['id'] ?>">
                                <img src="<?= escape($related['image'] ?: '/assets/images/placeholder.jpg') ?>" 
                                     alt="<?= escape($related['name']) ?>">
                            </a>
                            <div class="card-body">
                                <h5 class="card-title">
                                    <a href="<?= SITE_URL ?>/product.php?id=<?= $related['id'] ?>">
                                        <?= escape($related['name']) ?>
                                    </a>
                                </h5>
                                <button class="btn add-to-cart-btn" 
                                        data-product-id="<?= $related['id'] ?>"
                                        data-product-name="<?= escape($related['name']) ?>">
                                    <span><?= formatPrice($related['price']) ?></span>
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<script>
// Модальное окно выбора количества
let selectedProductId = null;
let selectedProductName = '';
let selectedProductPrice = 0;
let quantityModal = null;

document.addEventListener('DOMContentLoaded', function() {
    // Инициализация модального окна Bootstrap
    quantityModal = new bootstrap.Modal(document.getElementById('quantityModal'));
    
    // Обработчик кнопки добавления в корзину
    const addToCartBtn = document.querySelector('.add-to-cart-btn[data-product-id]');
    if (addToCartBtn) {
        addToCartBtn.addEventListener('click', function(e) {
            e.preventDefault();
            selectedProductId = parseInt(this.dataset.productId);
            selectedProductName = this.dataset.productName;
            selectedProductPrice = <?= $product['price'] ?>;
            
            // Заполняем модальное окно
            document.getElementById('modalProductName').textContent = selectedProductName;
            document.getElementById('modalProductPrice').textContent = '<?= formatPrice($product['price']) ?>';
            document.getElementById('modalQuantity').value = 1;
            updateModalTotal();
            
            // Показываем модальное окно
            quantityModal.show();
        });
    }
    
    // Уменьшение количества
    document.getElementById('modalQtyDecrease').addEventListener('click', function() {
        const input = document.getElementById('modalQuantity');
        let value = parseInt(input.value) - 1;
        if (value < 1) value = 1;
        input.value = value;
        updateModalTotal();
    });
    
    // Увеличение количества
    document.getElementById('modalQtyIncrease').addEventListener('click', function() {
        const input = document.getElementById('modalQuantity');
        let value = parseInt(input.value) + 1;
        if (value > 99) value = 99;
        input.value = value;
        updateModalTotal();
    });
    
    // Изменение количества вручную
    document.getElementById('modalQuantity').addEventListener('input', function() {
        let value = parseInt(this.value);
        if (isNaN(value) || value < 1) value = 1;
        if (value > 99) value = 99;
        this.value = value;
        updateModalTotal();
    });
    
    // Кнопка "Добавить в корзину" в модальном окне
    document.getElementById('modalAddToCart').addEventListener('click', function() {
        const quantity = parseInt(document.getElementById('modalQuantity').value);
        addToCart(selectedProductId, quantity);
        quantityModal.hide();
    });
});

function updateModalTotal() {
    const quantity = parseInt(document.getElementById('modalQuantity').value);
    const total = selectedProductPrice * quantity;
    // Форматируем цену как в PHP (разделитель тысяч - пробел, десятичных - запятая)
    const formattedTotal = total.toLocaleString('ru-RU', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₽';
    document.getElementById('modalTotalPrice').textContent = formattedTotal;
}
</script>

<!-- Модальное окно выбора количества -->
<div class="modal fade" id="quantityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Выберите количество</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0">
                <div class="text-center mb-3">
                    <p class="text-muted mb-1" id="modalProductName"></p>
                    <p class="fw-bold fs-4 text-primary mb-0" id="modalProductPrice"></p>
                </div>
                
                <div class="quantity-control-large mx-auto">
                    <button type="button" id="modalQtyDecrease" class="btn btn-outline-secondary rounded-circle" style="width: 50px; height: 50px; font-size: 1.5rem;">-</button>
                    <input type="number" 
                           id="modalQuantity" 
                           value="1" 
                           min="1" 
                           max="99"
                           class="form-control text-center mx-3"
                           style="width: 80px; font-size: 1.5rem; font-weight: 600;">
                    <button type="button" id="modalQtyIncrease" class="btn btn-outline-secondary rounded-circle" style="width: 50px; height: 50px; font-size: 1.5rem;">+</button>
                </div>
                
                <div class="text-center mt-3">
                    <p class="text-muted mb-0">Итого: <span class="fw-bold text-primary fs-5" id="modalTotalPrice"></span></p>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn add-to-cart-btn rounded-pill px-5" id="modalAddToCart">
                    <i class="fas fa-cart-plus me-2"></i>Добавить в корзину
                </button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/templates/footer.php'; ?>
