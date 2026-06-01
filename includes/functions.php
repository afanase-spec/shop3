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

// ============================================================
// ОТЗЫВЫ
// ============================================================

/**
 * Получить опубликованные отзывы на товар
 * @param int $productId
 * @param int $limit
 * @param int $offset
 * @return array
 */
function getApprovedReviews(int $productId, int $limit = 10, int $offset = 0): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT r.id, r.rating, r.comment, r.created_at,
               u.name AS user_name
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        WHERE r.product_id = ? AND r.status = 'approved'
        ORDER BY r.created_at DESC
        LIMIT " . (int)$limit . " OFFSET " . (int)$offset
    );
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

/**
 * Получить средний рейтинг и количество отзывов
 * В рейтинг идут approved + hidden, БЕЗ pending
 * @param int $productId
 * @return array ['average' => float, 'count_in_rating' => int, 'count_visible' => int]
 */
function getProductRating(int $productId): array {
    $db = getDB();
    
    // Средний рейтинг по approved + hidden
    $stmt = $db->prepare("
        SELECT 
            COALESCE(AVG(rating), 0) AS average,
            COUNT(*) AS count_in_rating
        FROM reviews
        WHERE product_id = ? AND status IN ('approved', 'hidden')
    ");
    $stmt->execute([$productId]);
    $rating = $stmt->fetch();
    
    // Количество видимых (только approved) — для подписи "На основе X отзывов"
    $stmt = $db->prepare("
        SELECT COUNT(*) AS count_visible
        FROM reviews
        WHERE product_id = ? AND status = 'approved'
    ");
    $stmt->execute([$productId]);
    $visible = $stmt->fetch();
    
    return [
        'average' => round((float)$rating['average'], 1),
        'count_in_rating' => (int)$rating['count_in_rating'],
        'count_visible' => (int)$visible['count_visible']
    ];
}

/**
 * Получить рейтинги для массива товаров (для каталога — одним запросом)
 * @param array $productIds
 * @return array [productId => ['average' => float, 'count' => int]]
 */
function getRatingsForProducts(array $productIds): array {
    if (empty($productIds)) return [];
    
    $db = getDB();
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $db->prepare("
        SELECT product_id,
               ROUND(AVG(rating), 1) AS average,
               COUNT(*) AS count
        FROM reviews
        WHERE product_id IN ($placeholders) AND status IN ('approved', 'hidden')
        GROUP BY product_id
    ");
    $stmt->execute($productIds);
    
    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        $result[(int)$row['product_id']] = [
            'average' => (float)$row['average'],
            'count' => (int)$row['count']
        ];
    }
    return $result;
}

/**
 * Создать новый отзыв (со статусом pending)
 * @param int $productId
 * @param int $userId
 * @param int $rating
 * @param string $comment
 * @return bool
 */
function createReview(int $productId, int $userId, int $rating, string $comment = ''): bool {
    if ($rating < 1 || $rating > 5) return false;
    
    $db = getDB();
    
    // Проверим что товар существует
    $stmt = $db->prepare("SELECT id FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    if (!$stmt->fetch()) return false;
    
    $stmt = $db->prepare("
        INSERT INTO reviews (product_id, user_id, rating, comment, status)
        VALUES (?, ?, ?, ?, 'pending')
    ");
    
    try {
        return $stmt->execute([$productId, $userId, $rating, trim($comment) ?: null]);
    } catch (Exception $e) {
        error_log("Review create error: " . $e->getMessage());
        return false;
    }
}

/**
 * Рендер 5 звёзд с дробным заполнением (для отображения)
 * Возвращает HTML строку со звёздами Font Awesome
 * @param float $rating  Например 4.3
 * @param string $size   Размер: 'sm' | 'md' | 'lg'
 * @return string HTML
 */
function renderStars(float $rating, string $size = 'md'): string {
    $sizeClass = ['sm' => 'stars-sm', 'md' => 'stars-md', 'lg' => 'stars-lg'][$size] ?? 'stars-md';
    
    $html = '<span class="rating-stars ' . $sizeClass . '">';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i) {
            // Полная звезда
            $html .= '<i class="fas fa-star star-filled"></i>';
        } elseif ($rating >= $i - 0.5) {
            // Половинка
            $html .= '<i class="fas fa-star-half-alt star-filled"></i>';
        } else {
            // Пустая
            $html .= '<i class="far fa-star star-empty"></i>';
        }
    }
    $html .= '</span>';
    return $html;
}

/**
 * Проверка, авторизован ли пользователь
 * @return bool
 */
function isUserLoggedIn(): bool {
    return isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
}