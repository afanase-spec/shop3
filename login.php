<?php
require_once __DIR__ . '/includes/functions.php';

// Если уже авторизован, редирект в зависимости от роли
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in']) {
    if (($_SESSION['user_role'] ?? 'user') === 'admin') {
        redirect('/admin/');
    } else {
        redirect('/');
    }
}

$error = '';

// Обработка входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = 'Введите email и пароль';
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_logged_in'] = true;
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role']  = $user['role'] ?? 'user';
                
                // Если пользователь — админ, ведём в админ-панель
                if ($_SESSION['user_role'] === 'admin') {
                    setFlashMessage('Добро пожаловать в админ-панель, ' . escape($user['name']) . '!', 'success');
                    redirect('/admin/');
                } else {
                    setFlashMessage('Добро пожаловать, ' . escape($user['name']) . '!', 'success');
                    redirect('/');
                }
            } else {
                $error = 'Неверный email или пароль';
            }
        }
    }
}

$pageTitle = 'Вход - ' . SITE_NAME;
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
        .login-card {
            max-width: 450px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card">
            <div class="card border-0 shadow-lg rounded-4 p-4">
                <div class="text-center mb-4">
                    <i class="fas fa-sign-in-alt fa-3x gradient-text mb-3"></i>
                    <h2 class="fw-bold">Вход в аккаунт</h2>
                    <p class="text-muted">Войдите для оформления заказов</p>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?= escape($error) ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" 
                               class="form-control" 
                               name="email" 
                               required 
                               autofocus
                               value="<?= escape($_POST['email'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Пароль</label>
                        <input type="password" 
                               class="form-control" 
                               name="password" 
                               required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 btn-lg mb-3">
                        <i class="fas fa-sign-in-alt me-2"></i>Войти
                    </button>
                </form>
                
                <div class="text-center">
                    <p class="text-muted mb-0">Нет аккаунта? 
                        <a href="<?= SITE_URL ?>/register.php" class="fw-semibold">Зарегистрироваться</a>
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
</body>
</html>