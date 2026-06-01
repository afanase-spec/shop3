<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Оформление заказа - ' . SITE_NAME;
$cart = getCart();
$error = '';
$success = false;
$orderNumber = 0;

// Обработка POST запроса
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка CSRF токена
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности. Попробуйте снова.';
    } elseif (empty($cart)) {
        $error = 'Корзина пуста';
    } else {
        // Получаем данные формы
        $customerName = trim($_POST['customer_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $comment = trim($_POST['comment'] ?? '');
        
        // Валидация
        if (empty($customerName) || mb_strlen($customerName) < 2) {
            $error = 'Введите корректное имя';
        } elseif (empty($phone) || !preg_match('/^[\d\s\-\+\(\)]+$/', $phone)) {
            $error = 'Введите корректный телефон';
        } elseif (empty($address)) {
            $error = 'Введите адрес доставки';
        }
        
        // Если ошибок нет, создаем заказ
        if (empty($error)) {
            try {
                $db = getDB();
                $total = calculateCartTotal();
                
                // Начинаем транзакцию
                $db->beginTransaction();
                
                // Создаем заказ
                $stmt = $db->prepare("INSERT INTO orders (customer_name, phone, address, comment, total) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$customerName, $phone, $address, $comment, $total]);
                $orderNumber = $db->lastInsertId();
                
                // Добавляем товары заказа
                foreach ($cart as $productId => $item) {
                    $stmt = $db->prepare("INSERT INTO order_items (order_id, product_id, quantity, price_at_time) VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $orderNumber,
                        $productId,
                        $item['quantity'],
                        $item['price']
                    ]);
                }
                
                // Подтверждаем транзакцию
                $db->commit();
                
                // Очищаем корзину
                clearCart();
                
                $success = true;
                
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Ошибка создания заказа: " . $e->getMessage());
                $error = 'Произошла ошибка при создании заказа. Попробуйте позже.';
            }
        }
    }
}

include __DIR__ . '/templates/header.php';
?>

<?php $breadcrumbs = [
    ['Корзина', '/cart.php'],
    ['Оформление заказа', null]
]; include __DIR__ . '/templates/breadcrumbs.php'; ?>

<div class="container my-5">
    <?php if ($success): ?>
        <div class="text-center py-5">
            <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
            <h1 class="mt-4 mb-3">Спасибо за заказ!</h1>
            <p class="lead text-muted mb-2">Ваш заказ успешно оформлен</p>
            <p class="text-muted mb-4">Номер заказа: <strong>#<?= $orderNumber ?></strong></p>
            <p class="text-muted mb-4">Мы свяжемся с вами в ближайшее время для подтверждения</p>
            <a href="<?= SITE_URL ?>/" class="btn btn-primary btn-lg">
                <i class="fas fa-home me-2"></i>Вернуться на главную
            </a>
        </div>
    <?php else: ?>
        <h1 class="section-title">Оформление заказа</h1>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= escape($error) ?></div>
        <?php endif; ?>
        
        <?php if (empty($cart)): ?>
            <div class="text-center py-5">
                <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
                <h3 class="text-muted">Корзина пуста</h3>
                <a href="<?= SITE_URL ?>/catalog.php" class="btn btn-primary mt-3">Перейти в каталог</a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm rounded-4 p-4">
                        <h4 class="fw-bold mb-4">Данные для доставки</h4>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">ФИО <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       name="customer_name" 
                                       required 
                                       minlength="2"
                                       placeholder="Иванов Иван Иванович"
                                       value="<?= escape($_POST['customer_name'] ?? '') ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Телефон <span class="text-danger">*</span></label>
                                <input type="tel" 
                                       class="form-control" 
                                       name="phone" 
                                       required
                                       pattern="[\d\s\-\+\(\)]+$/"
                                       placeholder="+7 (999) 123-45-67"
                                       value="<?= escape($_POST['phone'] ?? '') ?>">
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label fw-semibold mb-0">Адрес доставки <span class="text-danger">*</span></label>
                                    <button type="button" id="detect-address-btn" class="btn btn-sm btn-outline-primary py-1">
                                        <i class="fas fa-location-arrow me-1"></i> Определить адрес
                                    </button>
                                </div>
                                <textarea class="form-control" 
                                          id="address-field"
                                          name="address" 
                                          rows="3" 
                                          required
                                          placeholder="Город, улица, дом, квартира"><?= escape($_POST['address'] ?? '') ?></textarea>
                                <small id="geo-status" class="form-text text-muted d-none"></small>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Комментарий к заказу</label>
                                <textarea class="form-control" 
                                          name="comment" 
                                          rows="2" 
                                          placeholder="Дополнительная информация..."><?= escape($_POST['comment'] ?? '') ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 btn-lg">
                                <i class="fas fa-check me-2"></i>Подтвердить заказ
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm rounded-4 p-4">
                        <h4 class="fw-bold mb-4">Ваш заказ</h4>
                        
                        <?php foreach ($cart as $productId => $item): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <div>
                                    <span class="fw-semibold"><?= escape($item['name']) ?></span>
                                    <span class="text-muted small"> x<?= $item['quantity'] ?></span>
                                </div>
                                <span class="fw-bold"><?= formatPrice($item['price'] * $item['quantity']) ?></span>
                            </div>
                        <?php endforeach; ?>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between mb-3">
                            <span class="fw-bold fs-5">Итого:</span>
                            <span class="fw-bold fs-4 text-success"><?= formatPrice(calculateCartTotal()) ?></span>
                        </div>
                        
                        <a href="<?= SITE_URL ?>/cart.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-arrow-left me-2"></i>Вернуться в корзину
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    const detectBtn = document.getElementById('detect-address-btn');
    const addressField = document.getElementById('address-field');
    const statusText = document.getElementById('geo-status');
    
    // ВАШ КЛЮЧ HTTP ГЕОКОДЕРА
    const GEOCODER_API_KEY = '7f28dfaf-f690-4c44-b51f-cc2082634be0';

    if (!detectBtn) return;

    detectBtn.addEventListener('click', function () {
        detectBtn.disabled = true;
        detectBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Определяем...';
        showStatus('Запрашиваем геоданные устройства...', 'text-muted');

        // Шаг 1: Пробуем встроенную геолокацию HTML5 в браузере (Высокая точность GPS/Wi-Fi)
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function (position) {
                    // Успешно получили координаты устройства
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;
                    
                    showStatus('Координаты получены. Запрашиваем адрес у Яндекса...', 'text-muted');
                    // Важно: Яндекс Геокодер принимает координаты в формате "долгота,широта"
                    getAddressFromGeocoder(`${lon},${lat}`);
                },
                function (error) {
                    // Если браузер отказал или не смог найти GPS (например, на стационарном ПК по проводу)
                    console.warn("Браузерная геолокация недоступна, код ошибки: " + error.code);
                    showStatus('Точный GPS недоступен. Пробуем определить по IP-адресу...', 'text-warning');
                    
                    // Резервный вариант: Геокодирование на основе IP-адреса через сервис Яндекса
                    fetchAddressByIP();
                },
                { enableHighAccuracy: true, timeout: 6000, maximumAge: 0 }
            );
        } else {
            // Если браузер совсем древний и не поддерживает Geolocation
            fetchAddressByIP();
        }
    });

    // Шаг 2: Функция отправки координат в HTTP Геокодер Яндекса
    function getAddressFromGeocoder(geocodeValue) {
        const url = `https://geocode-maps.yandex.ru/1.x/?apikey=${GEOCODER_API_KEY}&geocode=${encodeURIComponent(geocodeValue)}&format=json&results=1`;

        fetch(url)
            .then(response => {
                if (!response.ok) throw new Error('Ошибка сети при запросе к геокодеру');
                return response.json();
            })
            .then(data => {
                try {
                    // Безопасно парсим сложную структуру JSON Яндекса
                    const geoObject = data.response.GeoObjectCollection.featureMember[0].GeoObject;
                    const addressLine = geoObject.metaDataProperty.GeocoderMetaData.Address.formatted;
                    
                    if (addressLine) {
                        addressField.value = addressLine;
                        showStatus('Адрес успешно определен!', 'text-success');
                    } else {
                        showStatus('Адрес найден, но не удалось прочесть строку. Введите вручную.', 'text-warning');
                    }
                } catch (e) {
                    console.error("Ошибка разбора JSON Яндекса:", e);
                    showStatus('Не удалось разобрать адрес. Пожалуйста, введите его вручную.', 'text-warning');
                }
                resetButton();
            })
            .catch(err => {
                console.error(err);
                showStatus('Ошибка сервера геокодирования. Введите адрес вручную.', 'text-danger');
                resetButton();
            });
    }

    // Шаг 3: Резервный метод определения положения по IP через API Яндекс.Карт (без координат устройства)
    function fetchAddressByIP() {
        // Запрашиваем автоопределение региона у Яндекса
        // Для этого делаем пустой запрос к Геокодеру, но Яндекс умеет подставлять город по IP заголовкам
        // Альтернативный надежный трюк: запрашиваем координаты через облегченный JSONP/скрипт API Яндекса
        const script = document.createElement('script');
        script.src = 'https://api-maps.yandex.ru/2.1/?lang=ru_RU&onload=initIPDetection';
        document.head.appendChild(script);
    }

    // Глобальная функция-колбэк, которая вызовется после загрузки легкого API карт для IP-геолокации
    window.initIPDetection = function() {
        if (typeof ymaps !== 'undefined' && ymaps.geolocation) {
            ymaps.geolocation.get({ provider: 'yandex', autoReverseGeocode: true })
                .then(function (result) {
                    const address = result.geoObjects.get(0).getAddressLine();
                    if (address) {
                        addressField.value = address;
                        showStatus('Адрес примерно определен по вашей сети.', 'text-success');
                    } else {
                        showStatus('Не удалось определить адрес по IP. Введите вручную.', 'text-warning');
                    }
                    resetButton();
                })
                .catch(function() {
                    showStatus('Автоопределение не удалось. Пожалуйста, введите адрес вручную.', 'text-warning');
                    resetButton();
                });
        } else {
            showStatus('Сервис геопозиции недоступен. Введите адрес вручную.', 'text-warning');
            resetButton();
        }
    };

    function showStatus(text, className) {
        statusText.textContent = text;
        statusText.className = 'form-text d-block ' + className;
    }

    function resetButton() {
        detectBtn.disabled = false;
        detectBtn.innerHTML = '<i class="fas fa-location-arrow me-1"></i> Определить адрес';
    }
});
</script>

<?php include __DIR__ . '/templates/footer.php'; ?>