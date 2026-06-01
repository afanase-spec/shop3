<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Корзина - ' . SITE_NAME;
$cart = getCart();

include __DIR__ . '/templates/header.php';
?>

<div class="container my-5">
    <h1 class="section-title">Корзина</h1>
    
    <?php if (empty($cart)): ?>
        <div class="text-center py-5">
            <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
            <h3 class="text-muted">Ваша корзина пуста</h3>
            <p class="text-muted mb-4">Добавьте товары из каталога</p>
            <a href="<?= SITE_URL ?>/catalog.php" class="btn btn-primary btn-lg">
                <i class="fas fa-shopping-bag me-2"></i>Перейти в каталог
            </a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <!-- Список товаров -->
            <div class="col-lg-8">
                <div class="cart-table table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Товар</th>
                                <th>Цена</th>
                                <th>Количество</th>
                                <th>Сумма</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart as $productId => $item): ?>
                                <tr data-product-id="<?= $productId ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?= escape($item['image'] ?? '/assets/images/placeholder.jpg') ?>" 
                                                 alt="<?= escape($item['name']) ?>"
                                                 style="width: 80px; height: 80px; object-fit: contain; background-color: #f8f9fa; border-radius: 8px; padding: 0.5rem; margin-right: 1rem;">
                                            <span class="fw-semibold"><?= escape($item['name']) ?></span>
                                        </div>
                                    </td>
                                    <td><?= formatPrice($item['price']) ?></td>
                                    <td>
                                        <div class="quantity-control">
                                            <button type="button" class="btn-qty-decrease" onclick="updateQuantity(<?= $productId ?>, <?= $item['quantity'] - 1 ?>)">−</button>
                                            <input type="number" id="qty-<?= $productId ?>" value="<?= $item['quantity'] ?>" readonly>
                                            <button type="button" class="btn-qty-increase" onclick="updateQuantity(<?= $productId ?>, <?= $item['quantity'] + 1 ?>)">+</button>
                                        </div>
                                    </td>
                                    <td class="fw-bold item-total" data-product-id="<?= $productId ?>">
                                        <?= formatPrice($item['price'] * $item['quantity']) ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(<?= $productId ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Итого -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <h4 class="fw-bold mb-4">Итого</h4>
                    
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Товары (<span id="cartItemsCount"><?= getCartCount() ?></span> шт.):</span>
                        <span class="fw-bold" id="cartTotal"><?= formatPrice(calculateCartTotal()) ?></span>
                    </div>
                    
                    <hr>
                    
                    <a href="<?= SITE_URL ?>/checkout.php" class="btn btn-primary w-100 btn-lg mt-3">
                        Оформить заказ <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                    
                    <a href="<?= SITE_URL ?>/catalog.php" class="btn btn-outline-secondary w-100 mt-2">
                        Продолжить покупки
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/templates/footer.php'; ?>