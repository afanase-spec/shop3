<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Генерация CSRF токена
 */
function generateCSRFToken(): string {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Проверка CSRF токена
 */
function validateCSRFToken(string $token): bool {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Защита от XSS
 */
function escape($data): string {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Форматирование цены
 */
function formatPrice($price): string {
    return number_format(floatval($price), 2, ',', ' ') . ' ₽';
}

/**
 * Редирект
 */
function redirect(string $url): void {
    header("Location: " . SITE_URL . $url);
    exit;
}

/**
 * Генерация slug из текста
 */
function generateSlug(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9а-яё-]/u', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

/**
 * Добавление товара в корзину
 */
function addToCart(int $productId, int $quantity = 1): bool {
    $db = getDB();
    
    // Проверяем существование товара
    $stmt = $db->prepare("SELECT id, name, price, image FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        return false;
    }
    
    // Инициализируем корзину если нужно
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Если товар уже в корзине, увеличиваем количество
    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId]['quantity'] += $quantity;
    } else {
        $_SESSION['cart'][$productId] = [
            'quantity' => $quantity,
            'price' => floatval($product['price']),
            'name' => $product['name'],
            'image' => $product['image'] ?? '/assets/images/placeholder.jpg'
        ];
    }
    
    return true;
}

/**
 * Получение корзины
 */
function getCart(): array {
    return $_SESSION['cart'] ?? [];
}

/**
 * Обновление количества товара в корзине
 */
function updateCartItem(int $productId, int $quantity): bool {
    if (!isset($_SESSION['cart'][$productId])) {
        return false;
    }
    
    if ($quantity <= 0) {
        unset($_SESSION['cart'][$productId]);
    } else {
        $_SESSION['cart'][$productId]['quantity'] = min($quantity, 99);
    }
    
    return true;
}

/**
 * Удаление товара из корзины
 */
function removeFromCart(int $productId): bool {
    if (!isset($_SESSION['cart'][$productId])) {
        return false;
    }
    
    unset($_SESSION['cart'][$productId]);
    return true;
}

/**
 * Очистка корзины
 */
function clearCart(): void {
    $_SESSION['cart'] = [];
}

/**
 * Подсчет общей суммы корзины
 */
function calculateCartTotal(): float {
    $cart = getCart();
    $total = 0;
    
    foreach ($cart as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    
    return $total;
}

/**
 * Подсчет количества товаров в корзине
 */
function getCartCount(): int {
    $cart = getCart();
    $count = 0;
    
    foreach ($cart as $item) {
        $count += $item['quantity'];
    }
    
    return $count;
}

/**
 * Получение flash сообщения
 */
function getFlashMessage(): ?array {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Установка flash сообщения
 */
function setFlashMessage(string $message, string $type = 'success'): void {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}
/**
 * Живой поиск товаров по названию
 * 
 * @param string $query Поисковая строка
 * @param int $limit Максимум результатов (по умолчанию 8)
 * @return array Массив товаров с полями id, name, price, image, category_name
 */
function searchProducts(string $query, int $limit = 8): array {
    $query = trim($query);
    
    // Минимум 2 символа для поиска
    if (mb_strlen($query) < 2) {
        return [];
    }
    
    $db = getDB();
    $searchTerm = '%' . $query . '%';
    
    $stmt = $db->prepare("
        SELECT p.id, p.name, p.price, p.image, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.name LIKE ?
        ORDER BY 
            CASE 
                WHEN p.name LIKE ? THEN 1
                ELSE 2
            END,
            p.name ASC
        LIMIT " . (int)$limit
    );
    
    // Первый параметр — общий поиск, второй — приоритет для совпадений в начале
    $stmt->execute([$searchTerm, $query . '%']);
    
    return $stmt->fetchAll();
}
