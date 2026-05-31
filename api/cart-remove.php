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

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Некорректный товар']);
    exit;
}

// Удаляем из корзины
if (removeFromCart($productId)) {
    echo json_encode([
        'success' => true,
        'message' => 'Товар удален из корзины',
        'cartCount' => getCartCount(),
        'cartTotal' => calculateCartTotal()
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Товар не найден в корзине']);
}
