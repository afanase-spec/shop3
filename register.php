<?php
require_once __DIR__ . '/includes/functions.php';

// Если уже авторизован, редирект на главную
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in']) {
    redirect('/');
}

$error = '';
$success = '';

/**
 * Валидация телефона: должен быть в формате +7(9XX) XXX-XX-XX
 * Принимаем любой ввод, но проверяем что после очистки получается 11 цифр (79XXXXXXXXX).
 */
function validatePhoneFormat($phone) {
    if (empty($phone)) return true; // телефон необязателен
    $digits = preg_replace('/\D/', '', $phone);
    // должно быть ровно 11 цифр, начинаться с 79
    return strlen($digits) === 11 && substr($digits, 0, 2) === '79';
}

/**
 * Нормализуем телефон к виду +7(9XX) XXX-XX-XX перед записью в БД
 */
function normalizePhone($phone) {
    if (empty($phone)) return '';
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) !== 11) return $phone;
    // 7 9 XX XXX XX XX
    return sprintf('+7(%s) %s-%s-%s',
        substr($digits, 1, 3),  // 9XX
        substr($digits, 4, 3),  // XXX
        substr($digits, 7, 2),  // XX
        substr($digits, 9, 2)   // XX
    );
}

// Обработка регистрации
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        // Валидация
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Введите корректный email';
        } elseif (empty($password) || strlen($password) < 6) {
            $error = 'Пароль должен быть не менее 6 символов';
        } elseif (empty($name)) {
            $error = 'Введите ваше имя';
        } elseif (!empty($phone) && !validatePhoneFormat($phone)) {
            $error = 'Телефон должен быть в формате +7(9XX) XXX-XX-XX';
        } else {
            $phone = normalizePhone($phone);
            $db = getDB();
            
            // Проверяем, существует ли пользователь
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error = 'Пользователь с таким email уже существует';
            } else {
                // Создаем нового пользователя
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (email, password_hash, name, phone) VALUES (?, ?, ?, ?)");
                
                try {
                    $stmt->execute([$email, $passwordHash, $name, $phone]);
                    
                    // Автоматический вход
                    $_SESSION['user_logged_in'] = true;
                    $_SESSION['user_id'] = $db->lastInsertId();
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    
                    setFlashMessage('Регистрация прошла успешно!', 'success');
                    redirect('/');
                } catch (Exception $e) {
                    $error = 'Ошибка при регистрации. Попробуйте позже.';
                    error_log("Registration error: " . $e->getMessage());
                }
            }
        }
    }
}

$pageTitle = 'Регистрация - ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/custom.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 2rem 0;
        }
        .register-card {
            max-width: 500px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-card">
            <div class="card border-0 shadow-lg rounded-4 p-4">
                <div class="text-center mb-4">
                    <i class="fas fa-user-plus fa-3x gradient-text mb-3"></i>
                    <h2 class="fw-bold">Регистрация</h2>
                    <p class="text-muted">Создайте аккаунт для удобных покупок</p>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?= escape($error) ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Имя <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               name="name" 
                               required 
                               value="<?= escape($_POST['name'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" 
                               class="form-control" 
                               name="email" 
                               required 
                               value="<?= escape($_POST['email'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Телефон</label>
                        <input type="tel" 
                               class="form-control" 
                               name="phone"
                               data-phone-mask
                               placeholder="+7(9XX) XXX-XX-XX"
                               value="<?= escape($_POST['phone'] ?? '') ?>">
                        <small class="text-muted">Формат: +7(9XX) XXX-XX-XX</small>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Пароль <span class="text-danger">*</span></label>
                        <input type="password" 
                               class="form-control" 
                               name="password" 
                               required
                               minlength="6">
                        <small class="text-muted">Минимум 6 символов</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 btn-lg mb-3">
                        <i class="fas fa-user-plus me-2"></i>Зарегистрироваться
                    </button>
                </form>
                
                <div class="text-center">
                    <p class="text-muted mb-0">Уже есть аккаунт? 
                        <a href="<?= SITE_URL ?>/login.php" class="fw-semibold">Войти</a>
                    </p>
                </div>
                
                <hr class="my-4">
                
                <div class="text-center">
                    <a href="<?= SITE_URL ?>/" class="text-muted">
                        <i class="fas fa-arrow-left me-1"></i>Вернуться на главную
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script src="<?= SITE_URL ?>/assets/js/phone-mask.js"></script>
</body>
</html>