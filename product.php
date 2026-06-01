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

// === ОБРАБОТКА ОТПРАВКИ ОТЗЫВА ===
$reviewError = '';
$reviewSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!isUserLoggedIn()) {
        $reviewError = 'Чтобы оставить отзыв, нужно войти в аккаунт';
    } elseif (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $reviewError = 'Ошибка безопасности';
    } else {
        $rating = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        
        if ($rating < 1 || $rating > 5) {
            $reviewError = 'Поставьте оценку от 1 до 5 звёзд';
        } elseif (createReview($productId, $_SESSION['user_id'], $rating, $comment)) {
            $reviewSuccess = 'Спасибо! Ваш отзыв отправлен на модерацию и появится после проверки.';
        } else {
            $reviewError = 'Не удалось сохранить отзыв. Попробуйте позже.';
        }
    }
}

// === ОТЗЫВЫ И РЕЙТИНГ ===
$rating = getProductRating($productId);
$reviews = getApprovedReviews($productId, 50);

$pageTitle = escape($product['name']) . ' - ' . SITE_NAME;

// Получаем похожие товары
$stmt = $db->prepare("SELECT * FROM products WHERE category_id = ? AND id != ? LIMIT 4");
$stmt->execute([$product['category_id'], $productId]);
$relatedProducts = $stmt->fetchAll();

include __DIR__ . '/templates/header.php';
?>

<?php
// Хлебные крошки
$breadcrumbs = [['Каталог', '/catalog.php']];
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
                
                <!-- РЕЙТИНГ ПОД НАЗВАНИЕМ -->
                <?php if ($rating['count_in_rating'] > 0): ?>
                    <div class="d-flex align-items-center mb-2 product-rating-summary">
                        <?= renderStars($rating['average'], 'md') ?>
                        <span class="ms-2 fw-bold"><?= number_format($rating['average'], 1, '.', '') ?></span>
                        <span class="text-muted ms-2">
                            (<?= $rating['count_visible'] ?> 
                            <?= $rating['count_visible'] === 1 ? 'отзыв' : ($rating['count_visible'] < 5 ? 'отзыва' : 'отзывов') ?>)
                        </span>
                        <a href="#reviews-section" class="ms-3 small text-decoration-none">К отзывам ↓</a>
                    </div>
                <?php else: ?>
                    <div class="mb-2 text-muted small">
                        <i class="far fa-star me-1"></i>Пока нет отзывов
                    </div>
                <?php endif; ?>
                
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
                            <div class="nutrition-row"><span class="nutrition-label">Калории</span><span class="nutrition-value"><?= $product['calories'] ?> ккал</span></div>
                            <div class="nutrition-row"><span class="nutrition-label">Белки</span><span class="nutrition-value"><?= $product['proteins'] ?> г</span></div>
                            <div class="nutrition-row"><span class="nutrition-label">Жиры</span><span class="nutrition-value"><?= $product['fats'] ?> г</span></div>
                            <div class="nutrition-row"><span class="nutrition-label">Углеводы</span><span class="nutrition-value"><?= $product['carbohydrates'] ?> г</span></div>
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
    
    <!-- ========================================
         БЛОК ОТЗЫВОВ
    ========================================= -->
    <section id="reviews-section" class="mt-5">
        <h2 class="section-title">Отзывы и оценки</h2>
        
        <div class="row g-4">
            <!-- ЛЕВО: Сводка рейтинга + форма отправки -->
            <div class="col-lg-4">
                <!-- Большой рейтинг -->
                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 text-center rating-summary-card">
                    <?php if ($rating['count_in_rating'] > 0): ?>
                        <div class="display-3 fw-bold mb-2"><?= number_format($rating['average'], 1, '.', '') ?></div>
                        <div class="mb-2"><?= renderStars($rating['average'], 'lg') ?></div>
                        <p class="text-muted mb-0">
                            На основе <?= $rating['count_visible'] ?> 
                            <?= $rating['count_visible'] === 1 ? 'отзыва' : 'отзывов' ?>
                        </p>
                    <?php else: ?>
                        <i class="far fa-star fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-0">У этого товара пока нет отзывов</p>
                        <p class="small text-muted mt-2">Будьте первым!</p>
                    <?php endif; ?>
                </div>
                
                <!-- Форма отправки отзыва -->
                <div class="card border-0 shadow-sm rounded-4 p-4 review-form-card">
                    <h5 class="fw-bold mb-3"><i class="fas fa-pen me-2 text-primary"></i>Оставить отзыв</h5>
                    
                    <?php if (!empty($reviewError)): ?>
                        <div class="alert alert-danger"><?= escape($reviewError) ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($reviewSuccess)): ?>
                        <div class="alert alert-success"><?= escape($reviewSuccess) ?></div>
                    <?php endif; ?>
                    
                    <?php if (!isUserLoggedIn()): ?>
                        <p class="text-muted mb-3">Чтобы оставить отзыв, войдите в аккаунт.</p>
                        <a href="<?= SITE_URL ?>/login.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-sign-in-alt me-2"></i>Войти
                        </a>
                        <a href="<?= SITE_URL ?>/register.php" class="btn btn-link w-100 mt-2">
                            Или зарегистрироваться
                        </a>
                    <?php else: ?>
                        <form method="POST" action="<?= SITE_URL ?>/product.php?id=<?= $productId ?>#reviews-section">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="submit_review" value="1">
                            
                            <!-- Звёзды для ввода оценки -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Ваша оценка <span class="text-danger">*</span></label>
                                <div class="rating-input" id="ratingInput">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <input type="radio" name="rating" id="rating-<?= $i ?>" value="<?= $i ?>" required>
                                        <label for="rating-<?= $i ?>" class="rating-input-star" title="<?= $i ?> из 5">
                                            <i class="fas fa-star"></i>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <!-- Текст отзыва -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Комментарий <span class="text-muted small">(необязательно)</span></label>
                                <textarea name="comment" class="form-control" rows="4" 
                                          maxlength="2000"
                                          placeholder="Поделитесь впечатлениями..."><?= escape($_POST['comment'] ?? '') ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-paper-plane me-2"></i>Отправить отзыв
                            </button>
                            <p class="small text-muted text-center mt-2 mb-0">
                                Отзыв появится после модерации
                            </p>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ПРАВО: Список отзывов -->
            <div class="col-lg-8">
                <?php if (empty($reviews)): ?>
                    <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
                        <i class="far fa-comment-dots fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-0">Пока никто не оставил отзыв на этот товар.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($reviews as $rev): ?>
                        <div class="card border-0 shadow-sm rounded-4 p-4 mb-3 review-card">
                            <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap">
                                <div>
                                    <div class="fw-bold mb-1"><?= escape($rev['user_name']) ?></div>
                                    <?= renderStars((float)$rev['rating'], 'sm') ?>
                                </div>
                                <div class="text-muted small">
                                    <?= date('d.m.Y', strtotime($rev['created_at'])) ?>
                                </div>
                            </div>
                            <?php if (!empty($rev['comment'])): ?>
                                <p class="mb-0 mt-2 review-text"><?= nl2br(escape($rev['comment'])) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
    
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
// Модальное окно выбора количества (без изменений)
let selectedProductId = null;
let selectedProductName = '';
let selectedProductPrice = 0;
let quantityModal = null;

document.addEventListener('DOMContentLoaded', function() {
    quantityModal = new bootstrap.Modal(document.getElementById('quantityModal'));
    
    const addToCartBtn = document.querySelector('.product-detail .add-to-cart-btn[data-product-id="<?= $product['id'] ?>"]');
    if (addToCartBtn) {
        addToCartBtn.addEventListener('click', function(e) {
            e.preventDefault();
            selectedProductId = parseInt(this.dataset.productId);
            selectedProductName = this.dataset.productName;
            selectedProductPrice = <?= $product['price'] ?>;
            
            document.getElementById('modalProductName').textContent = selectedProductName;
            document.getElementById('modalProductPrice').textContent = '<?= formatPrice($product['price']) ?>';
            document.getElementById('modalQuantity').value = 1;
            updateModalTotal();
            quantityModal.show();
        });
    }
    
    document.getElementById('modalQtyDecrease').addEventListener('click', function() {
        const input = document.getElementById('modalQuantity');
        let value = parseInt(input.value) - 1;
        if (value < 1) value = 1;
        input.value = value;
        updateModalTotal();
    });
    
    document.getElementById('modalQtyIncrease').addEventListener('click', function() {
        const input = document.getElementById('modalQuantity');
        let value = parseInt(input.value) + 1;
        if (value > 99) value = 99;
        input.value = value;
        updateModalTotal();
    });
    
    document.getElementById('modalQuantity').addEventListener('input', function() {
        let value = parseInt(this.value);
        if (isNaN(value) || value < 1) value = 1;
        if (value > 99) value = 99;
        this.value = value;
        updateModalTotal();
    });
    
    document.getElementById('modalAddToCart').addEventListener('click', function() {
        const quantity = parseInt(document.getElementById('modalQuantity').value);
        addToCart(selectedProductId, quantity);
        quantityModal.hide();
    });
});

function updateModalTotal() {
    const quantity = parseInt(document.getElementById('modalQuantity').value);
    const total = selectedProductPrice * quantity;
    const formattedTotal = total.toLocaleString('ru-RU', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₽';
    document.getElementById('modalTotalPrice').textContent = formattedTotal;
}
</script>

<!-- Модальное окно выбора количества (без изменений) -->
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
                    <input type="number" id="modalQuantity" value="1" min="1" max="99" class="form-control text-center mx-3" style="width: 80px; font-size: 1.5rem; font-weight: 600;">
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