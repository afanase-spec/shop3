<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Проверка метода
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Неверный метод']);
    exit;
}

// Проверка CSRF токена
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Ошибка безопасности']);
    exit;
}

// Получаем параметры
$productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Некорректный товар']);
    exit;
}

// Ограничиваем количество
$quantity = max(1, min(99, $quantity));

// Обновляем корзину
if (updateCartItem($productId, $quantity)) {
    // Получаем обновленную сумму товара
    $cart = getCart();
    $itemTotal = isset($cart[$productId]) ? $cart[$productId]['price'] * $cart[$productId]['quantity'] : 0;
    
    echo json_encode([
        'success' => true,
        'message' => 'Количество обновлено',
        'itemTotal' => $itemTotal,
        'cartTotal' => calculateCartTotal(),
        'cartCount' => getCartCount()
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Ошибка обновления']);
}
