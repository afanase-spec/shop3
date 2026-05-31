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
$quantity = isset($_POST['quantity']) ? max(1, intval($_POST['quantity'])) : 1;

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Некорректный товар']);
    exit;
}

// Добавляем в корзину
if (addToCart($productId, $quantity)) {
    // Получаем текущее количество товара в корзине
    $cart = getCart();
    $currentQty = isset($cart[$productId]) ? $cart[$productId]['quantity'] : $quantity;
    
    echo json_encode([
        'success' => true,
        'message' => 'Товар добавлен в корзину',
        'cartCount' => getCartCount(),
        'cartTotal' => calculateCartTotal(),
        'currentQty' => $currentQty
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Товар не найден']);
}
