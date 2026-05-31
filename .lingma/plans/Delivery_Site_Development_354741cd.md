# Разработка сайта доставки продуктов

## Архитектура проекта

**Структура папок:**
```
c:\OSPanel\domains\shop2\
├── assets/
│   ├── css/
│   │   ├── bootstrap.min.css (CDN или локально)
│   │   └── custom.css (кастомные стили)
│   ├── js/
│   │   ├── bootstrap.bundle.min.js (CDN или локально)
│   │   └── main.js (AJAX, интерактивность)
│   └── images/
├── includes/
│   ├── config.php (константы, настройки)
│   ├── db.php (PDO подключение)
│   ├── functions.php (вспомогательные функции)
│   └── auth.php (проверка авторизации админа)
├── admin/
│   ├── login.php
│   ├── logout.php
│   ├── index.php (дашборд)
│   ├── products.php (управление товарами)
│   ├── categories.php (управление категориями)
│   └── orders.php (управление заказами)
├── templates/
│   ├── header.php
│   └── footer.php
├── api/
│   ├── cart-add.php (AJAX добавление в корзину)
│   ├── cart-remove.php (AJAX удаление из корзины)
│   ├── cart-update.php (AJAX обновление количества)
│   └── filter-products.php (AJAX фильтрация)
├── index.php (главная)
├── catalog.php (каталог)
├── product.php (карточка товара)
├── cart.php (корзина)
├── checkout.php (оформление заказа)
├── contact.php (контакты)
└── install.sql (SQL дамп)
```

## Этап 1: База данных и конфигурация

### 1.1 SQL дамп (install.sql)
Создать файл `install.sql` со следующими таблицами:

**Таблица categories:**
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- name (VARCHAR(100), NOT NULL)
- slug (VARCHAR(100), UNIQUE, NOT NULL)
- created_at (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)

**Таблица products:**
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- category_id (INT, FOREIGN KEY -> categories.id)
- name (VARCHAR(200), NOT NULL)
- slug (VARCHAR(200), UNIQUE, NOT NULL)
- description (TEXT)
- price (DECIMAL(10,2), NOT NULL)
- image (VARCHAR(255)) - URL изображения или путь к файлу
- is_popular (TINYINT(1), DEFAULT 0)
- created_at (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)

**Таблица orders:**
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- customer_name (VARCHAR(200), NOT NULL)
- phone (VARCHAR(20), NOT NULL)
- address (TEXT, NOT NULL)
- comment (TEXT)
- status (ENUM('new', 'processing', 'delivered'), DEFAULT 'new')
- total (DECIMAL(10,2), NOT NULL)
- created_at (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)

**Таблица order_items:**
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- order_id (INT, FOREIGN KEY -> orders.id)
- product_id (INT, FOREIGN KEY -> products.id)
- quantity (INT, NOT NULL)
- price_at_time (DECIMAL(10,2), NOT NULL)

**Таблица admins:**
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- username (VARCHAR(50), UNIQUE, NOT NULL)
- password_hash (VARCHAR(255), NOT NULL)
- created_at (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP)

**Тестовые данные:**
- 2-3 категории (Продукты, Бытовая химия, Напитки)
- 5-8 товаров с разными категориями, ценами, флагом is_popular
- 1 админ: username='admin', password_hash от 'admin123' (сгенерировать через password_hash())

### 1.2 Конфигурационные файлы

**includes/config.php:**
```php
<?php
session_start();
date_default_timezone_set('Europe/Moscow');

// Константы БД
define('DB_HOST', 'localhost');
define('DB_NAME', 'delivery');
define('DB_USER', 'root');
define('DB_PASS', '');

// Константы сайта
define('SITE_URL', 'http://shop2');
define('SITE_NAME', 'Доставка Продуктов');

// Настройки безопасности
define('CSRF_TOKEN_NAME', 'csrf_token');
?>
```

**includes/db.php:**
```php
<?php
require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Ошибка подключения к БД: " . $e->getMessage());
        }
    }
    return $pdo;
}
?>
```

**includes/functions.php:**
Ключевые функции:
- `generateCSRFToken()` - генерация CSRF токена
- `validateCSRFToken($token)` - проверка CSRF токена
- `escape($data)` - защита от XSS (htmlspecialchars)
- `formatPrice($price)` - форматирование цены
- `redirect($url)` - редирект
- `addToCart($productId, $quantity)` - добавление в корзину (сессия)
- `getCart()` - получение корзины
- `updateCartItem($productId, $quantity)` - обновление количества
- `removeFromCart($productId)` - удаление из корзины
- `clearCart()` - очистка корзины
- `calculateCartTotal()` - подсчет суммы корзины

**includes/auth.php:**
Проверка авторизации админа:
```php
<?php
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    redirect('/admin/login.php');
}
?>
```

## Этап 2: Шаблоны и базовый дизайн

### 2.1 templates/header.php
Bootstrap 5 через CDN + Font Awesome 6 + Google Fonts (Inter/Poppins)

Структура:
- Навбар с логотипом (градиентный текст)
- Меню: Главная, Каталог, Контакты
- Иконка корзины с badge (количество товаров) - кликабельная
- Ссылка на админку (если авторизован)
- Мобильное бургер-меню

CSS кастомизация (assets/css/custom.css):
- Градиенты для кнопок и заголовков
- Glassmorphism эффекты (backdrop-filter)
- Мягкие тени (box-shadow)
- Закругленные углы (border-radius: 16px для карточек)
- Hover-эффекты с transition
- Анимации fade-in при скролле

### 2.2 templates/footer.php
- Контакты (телефон, email, адрес)
- Копирайт
- Ссылки на соцсети (иконки Font Awesome)

## Этап 3: Публичные страницы

### 3.1 index.php (Главная)
Hero-блок:
- Большой заголовок с градиентом
- Подзаголовок
- Кнопка CTA "Перейти в каталог" (градиентная)
- Фоновое изображение или градиент

Секция категорий:
- Карточки категорий с иконками (Font Awesome)
- Grid layout (3-4 колонки на десктопе, 1 на мобильном)

Секция популярных товаров:
- Запрос к БД: SELECT * FROM products WHERE is_popular = 1 LIMIT 4
- Карточки товаров с изображением, названием, ценой, кнопкой "В корзину"
- AJAX добавление в корзину (без перезагрузки)

### 3.2 catalog.php (Каталог)
Параметры URL:
- `?category=id` - фильтр по категории
- `?search=текст` - поиск по названию
- `?page=номер` - пагинация

Логика:
- Построение SQL запроса с JOIN categories
- Применение фильтров (WHERE category_id = ? AND name LIKE ?)
- Пагинация (LIMIT 9 OFFSET ...)
- Вывод товаров карточками в grid (3 колонки)
- Боковая панель с фильтрами (список категорий)
- Поле поиска

AJAX функционал:
- При изменении категории или поиске - AJAX запрос к api/filter-products.php
- Обновление списка товаров без перезагрузки
- Обновление URL через history.pushState

Карточка товара:
- Изображение (placeholder если нет)
- Название
- Цена (крупным шрифтом)
- Кнопка "В корзину" с количеством (input type="number")
- Hover-эффект (увеличение тени, легкое поднятие)

### 3.3 product.php?id=X (Карточка товара)
Запрос к БД:
```sql
SELECT p.*, c.name as category_name 
FROM products p 
JOIN categories c ON p.category_id = c.id 
WHERE p.id = ?
```

Отображение:
- Большое изображение товара
- Название (H1)
- Категория (badge)
- Описание
- Цена (крупно)
- Форма с выбором количества и кнопкой "Добавить в корзину" (AJAX)

Рекомендации:
- Запрос: SELECT * FROM products WHERE category_id = ? AND id != ? LIMIT 4
- Горизонтальный скролл или grid маленьких карточек

### 3.4 cart.php (Корзина)
Чтение корзины из сессии `$_SESSION['cart']`

Если корзина пуста:
- Показать сообщение "Корзина пуста"
- Кнопка "Перейти в каталог"

Если есть товары:
- Таблица с товарами:
  - Изображение (миниатюра)
  - Название (ссылка на товар)
  - Цена за единицу
  - Количество (кнопки +/- и input)
  - Сумма (цена * количество)
  - Кнопка удаления (иконка корзины)
- Итоговая сумма (крупно)
- Кнопка "Оформить заказ"

AJAX функционал:
- При изменении количества - AJAX к api/cart-update.php
- При удалении - AJAX к api/cart-remove.php
- Обновление итоговой суммы и badge в хедере
- Анимация обновления (fade effect)

JavaScript (assets/js/main.js):
- Обработчики событий для кнопок +/- 
- Функции updateQuantity(), removeItem()
- Fetch API для AJAX запросов
- Обновление DOM после ответа сервера

### 3.5 checkout.php (Оформление заказа)
Форма с полями:
- ФИО (required, minlength=2)
- Телефон (required, pattern для валидации)
- Адрес доставки (required, textarea)
- Комментарий (textarea, optional)
- CSRF токен (hidden input)

Валидация:
- JavaScript валидация перед отправкой (HTML5 validation + custom JS)
- PHP валидация на сервере (проверка required полей, sanitize данных)

Обработка POST:
1. Проверка CSRF токена
2. Валидация входных данных
3. Получение корзины из сессии
4. Если корзина пуста - ошибка
5. Подсчет общей суммы
6. Транзакция БД:
   - INSERT INTO orders (...) VALUES (...)
   - Получение lastInsertId()
   - Для каждого товара в корзине: INSERT INTO order_items (...)
7. Очистка корзины: clearCart()
8. Редирект на страницу успеха или показ сообщения

Страница успеха:
- Сообщение "Спасибо за заказ!"
- Номер заказа
- Кнопка "Вернуться на главную"

### 3.6 contact.php (Контакты)
- Информация о доставке (время, стоимость, зоны)
- Контактные данные (телефон, email, адрес)
- Карта (можно iframe Яндекс.Карт или просто статичное изображение)
- Форма обратной связи (опционально)

## Этап 4: Административная панель

### 4.1 admin/login.php
Форма входа:
- Username (text input)
- Password (password input)
- CSRF токен
- Кнопка "Войти"

Обработка POST:
1. Проверка CSRF
2. Поиск пользователя в БД по username
3. Проверка пароля: password_verify($password, $row['password_hash'])
4. Если успешно: `$_SESSION['admin_logged_in'] = true`, редирект на /admin/
5. Если ошибка: показать сообщение об ошибке

Дизайн:
- Центрированная карточка на странице
- Минималистичный дизайн
- Логотип сайта сверху

### 4.2 admin/logout.php
```php
<?php
session_destroy();
redirect('/admin/login.php');
?>
```

### 4.3 admin/index.php (Дашборд)
Подключить includes/auth.php для проверки авторизации

Статистика (карточки):
- Количество заказов сегодня: SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()
- Общая сумма заказов сегодня: SELECT SUM(total) FROM orders WHERE DATE(created_at) = CURDATE()
- Всего товаров: SELECT COUNT(*) FROM products
- Заказов со статусом 'new': SELECT COUNT(*) FROM orders WHERE status = 'new'

Последние заказы (таблица):
- SELECT * FROM orders ORDER BY created_at DESC LIMIT 10
- Колонки: ID, Дата, Клиент, Телефон, Сумма, Статус (badge цветом)
- Кнопка "Просмотр" (ссылка на orders.php с фильтром по id)

Быстрые действия (кнопки):
- Добавить товар
- Посмотреть заказы
- Управление категориями

### 4.4 admin/products.php (Управление товарами)
Подключить auth.php

Два режима: список и форма редактирования/добавления

**Режим списка (по умолчанию):**
- Таблица товаров:
  - ID, Изображение (миниатюра), Название, Категория, Цена, Популярный (да/нет), Действия
  - Кнопки: Редактировать (?edit=id), Удалить (форма POST с подтверждением)
- Кнопка "Добавить товар" (+)

Пагинация (по 15 товаров на страницу)

**Режим формы (?edit=id или ?add):**
Поля формы:
- Название (required)
- Категория (select из таблицы categories, required)
- Описание (textarea)
- Цена (number, step=0.01, required)
- Изображение (URL input или file upload - для простоты URL)
- Чекбокс "Популярный товар"
- CSRF токен

Обработка POST:
- Валидация данных
- Генерация slug: strtolower(str_replace(' ', '-', $name))
- Если edit: UPDATE products SET ... WHERE id = ?
- Если add: INSERT INTO products (...) VALUES (...)
- Редирект на products.php с сообщением об успехе

Удаление:
- Форма POST с product_id
- DELETE FROM products WHERE id = ?
- Проверка: нет ли товаров в заказах (опционально)

### 4.5 admin/categories.php (Управление категориями)
Аналогично products.php, но проще

**Список категорий:**
- Таблица: ID, Название, Slug, Количество товаров (COUNT), Действия
- Кнопка "Добавить категорию"

**Форма добавления/редактирования:**
- Название (required)
- Slug (auto-generate из названия, можно редактировать)
- CSRF токен

При удалении категории:
- Проверить, есть ли товары в этой категории
- Если есть - запретить удаление или предложить перенести товары
- CASCADE DELETE (опционально)

### 4.6 admin/orders.php (Управление заказами)
Подключить auth.php

Фильтры:
- По статусу: все, новые, в обработке, доставлены
- По дате: сегодня, неделя, месяц, все время

Список заказов (таблица):
- ID, Дата, Клиент, Телефон, Адрес (сокращенно), Сумма, Статус (dropdown для изменения), Действия
- Кнопка "Просмотр деталей"

Изменение статуса:
- Dropdown прямо в таблице (onchange submit формы)
- Или отдельная форма в модальном окне

**Просмотр деталей заказа (?view=id):**
- Информация о заказе: ID, Дата, Статус, Клиент, Телефон, Адрес, Комментарий
- Таблица товаров заказа:
  - SELECT oi.*, p.name as product_name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?
  - Колонки: Товар, Количество, Цена за шт., Сумма
- Итоговая сумма
- Кнопка "Назад к списку"
- Кнопка изменения статуса (форма POST)

## Этап 5: AJAX API endpoints

### 5.1 api/cart-add.php
POST request
Параметры: product_id, quantity (default 1), csrf_token

Логика:
- Проверка CSRF
- Валидация product_id (intval)
- Проверка существования товара в БД
- Вызов addToCart($productId, $quantity)
- Возврат JSON: {success: true, cartCount: X, message: "Товар добавлен"}

### 5.2 api/cart-remove.php
POST request
Параметры: product_id, csrf_token

Логика:
- Проверка CSRF
- Вызов removeFromCart($productId)
- Возврат JSON: {success: true, cartCount: X, cartTotal: Y}

### 5.3 api/cart-update.php
POST request
Параметры: product_id, quantity, csrf_token

Логика:
- Проверка CSRF
- Валидация quantity (min 1, max 99)
- Вызов updateCartItem($productId, $quantity)
- Возврат JSON: {success: true, itemTotal: X, cartTotal: Y, cartCount: Z}

### 5.4 api/filter-products.php
GET request
Параметры: category (optional), search (optional), page (optional)

Логика:
- Построение SQL запроса с фильтрами
- Пагинация
- Возврат HTML разметки карточек товаров или JSON с данными
- Лучше возвращать HTML для простоты замены DOM

## Этап 6: JavaScript функционал (assets/js/main.js)

### 6.1 Корзина
Функции:
- `addToCart(productId, quantity)` - AJAX POST к api/cart-add.php
- `removeFromCart(productId)` - AJAX POST к api/cart-remove.php
- `updateQuantity(productId, quantity)` - AJAX POST к api/cart-update.php
- `updateCartBadge(count)` - обновление badge в хедере
- `showNotification(message, type)` - всплывающее уведомление (toast)

Обработчики событий:
- Кнопки "В корзину" на всех страницах
- Кнопки +/- в корзине
- Кнопки удаления в корзине
- Prevent default для форм, обработка через fetch API

### 6.2 Фильтрация каталога
- Обработчик change на select категории
- Обработчик input на поле поиска (с debounce 300ms)
- Fetch GET к api/filter-products.php
- Замена содержимого контейнера товаров
- Обновление URL через history.pushState

### 6.3 Уведомления
- Создание toast уведомлений (Bootstrap Toast или кастомные)
- Автоматическое скрытие через 3 секунды
- Позиционирование: top-right corner

### 6.4 Анимации
- Intersection Observer для fade-in анимаций при скролле
- Добавление класса .animate-fade-in когда элемент входит в viewport

## Этап 7: Стилизация (assets/css/custom.css)

### 7.1 Переменные CSS
```css
:root {
  --primary-color: #2ecc71;
  --primary-dark: #27ae60;
  --secondary-color: #ff7e5e;
  --bg-light: #f8f9fa;
  --text-dark: #2c3e50;
  --shadow-sm: 0 2px 4px rgba(0,0,0,0.1);
  --shadow-md: 0 10px 15px -5px rgba(0,0,0,0.1);
  --shadow-lg: 0 20px 25px -5px rgba(0,0,0,0.15);
  --radius: 16px;
  --transition: all 0.3s ease;
}
```

### 7.2 Глобальные стили
- body: font-family: 'Inter', sans-serif; background: var(--bg-light)
- h1-h6: font-weight: 600, color: var(--text-dark)
- a: text-decoration: none, color inherit
- .btn-primary: gradient background, hover эффект (transform: translateY(-2px))

### 7.3 Компоненты
**Карточки товаров:**
- border-radius: var(--radius)
- box-shadow: var(--shadow-md)
- transition: var(--transition)
- &:hover: transform: translateY(-5px), box-shadow: var(--shadow-lg)

**Кнопки:**
- Gradient backgrounds
- Hover: brightness increase или gradient shift
- Active: scale(0.98)

**Навбар:**
- backdrop-filter: blur(10px) для glassmorphism эффекта
- box-shadow при скролле

**Hero блок:**
- Gradient background (linear-gradient)
- Large typography
- Animated elements (optional)

**Формы:**
- Input focus: border-color change, box-shadow
- Validation states (red/green borders)

**Адаптивность:**
- Media queries для tablet (768px) и mobile (576px)
- Burger menu для мобильных
- Grid адаптация (3 -> 2 -> 1 колонка)

## Этап 8: Безопасность и валидация

### 8.1 Защита от SQL инъекций
- Использование подготовленных запросов PDO (prepare + execute с параметрами)
- Никакой конкатенации пользовательских данных в SQL

### 8.2 Защита от XSS
- Функция escape() использует htmlspecialchars($data, ENT_QUOTES, 'UTF-8')
- Применять ко всем выводимым данным из БД или $_GET/$_POST

### 8.3 CSRF защита
- Генерация токена: bin2hex(random_bytes(32))
- Сохранение в сессии: $_SESSION['csrf_token']
- Добавление в каждую форму: <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
- Проверка при обработке POST: validateCSRFToken($_POST['csrf_token'])

### 8.4 Хеширование паролей
- При создании админа: password_hash('admin123', PASSWORD_DEFAULT)
- При проверке: password_verify($inputPassword, $storedHash)

### 8.5 Валидация входных данных
- intval() для числовых параметров
- trim() + strip_tags() для текстовых
- Filter_var для email, URL
- Проверка required полей
- Ограничение длины строк

## Этап 9: Тестирование

### 9.1 Функциональное тестирование
- Регистрация/авторизация админа
- CRUD операции с товарами и категориями
- Добавление/удаление/обновление корзины
- Оформление заказа
- Просмотр заказов в админке
- Изменение статуса заказа

### 9.2 Безопасность
- Попытка SQL инъекции в формах
- XSS через поля ввода
- Доступ к admin pages без авторизации
- CSRF атаки (подделка форм)

### 9.3 Адаптивность
- Тестирование на разных разрешениях (1920px, 1366px, 768px, 375px)
- Проверка мобильного меню
- Корректность отображения таблиц на мобильных

### 9.4 AJAX функционал
- Работа корзины без перезагрузки
- Фильтрация товаров
- Обновление badge корзины
- Обработка ошибок сети

## Этап 10: Инструкция по развертыванию (README.md)

Создать файл README.md с инструкцией:

**Шаги установки:**
1. Поместить папку shop2 в OSPanel/domains/
2. Запустить OpenServer
3. Создать базу данных 'delivery' через phpMyAdmin
4. Импортировать файл install.sql в базу delivery
5. Проверить настройки в includes/config.php (при необходимости изменить SITE_URL)
6. Открыть браузер: http://shop2/
7. Админ-панель: http://shop2/admin/
   - Логин: admin
   - Пароль: admin123

**Требования:**
- PHP 7.4+
- MySQL 5.7+
- Включенные расширения: pdo_mysql, session

**Структура БД:**
- Краткое описание таблиц

**Возможные проблемы:**
- Ошибка подключения к БД: проверить config.php
- Не работают сессии: проверить права на запись в temp папку
- Не загружаются изображения: проверить пути

## Ключевые моменты реализации

### Генерация slug
```php
function generateSlug($text) {
    $text = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9-]/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}
```

### Работа с корзиной в сессии
Структура $_SESSION['cart']:
```php
[
    productId => [
        'quantity' => 2,
        'price' => 150.00,
        'name' => 'Товар 1'
    ],
    ...
]
```

### Пагинация
```php
$itemsPerPage = 9;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $itemsPerPage;
$totalItems = /* COUNT query */;
$totalPages = ceil($totalItems / $itemsPerPage);
```

### AJAX response format
Все API endpoints возвращают JSON:
```json
{
    "success": true/false,
    "message": "Описание результата",
    "data": {...} // дополнительные данные
}
```

### Обработка ошибок
- Try-catch для PDO операций
- Логирование ошибок (error_log)
- Пользовательские сообщения об ошибках (без технических деталей)

## Примечания

1. **Изображения**: Для простоты используем URL изображений (можно брать с placeholder сервисов или Unsplash). Загрузка файлов - опционально, требует дополнительной обработки (move_uploaded_file, проверка типа файла, генерация уникального имени).

2. **SEO**: Можно добавить meta-теги, Open Graph, но это не критично для MVP.

3. **Производительность**: 
   - Индексы в БД на часто используемых полях (category_id, status, slug)
   - Кэширование не требуется для небольшого сайта

4. **Расширения в будущем**:
   - Регистрация покупателей
   - История заказов для пользователей
   - Онлайн оплата
   - SMS/email уведомления
   - Отзывы и рейтинги товаров

Этот план обеспечивает создание полнофункционального, безопасного и современного сайта доставки с чистым кодом и хорошей архитектурой.