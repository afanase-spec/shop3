<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($pageTitle ?? SITE_NAME) ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/custom.css">
</head>
<body>
    <!-- Навбар -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold gradient-text" href="<?= SITE_URL ?>/">
                <i class="fas fa-shopping-basket me-2"></i><?= SITE_NAME ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= SITE_URL ?>/">Главная</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= SITE_URL ?>/catalog.php">Каталог</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= SITE_URL ?>/contact.php">Контакты</a>
                    </li>
                    <li class="nav-item ms-lg-3">
                        <a class="nav-link cart-link position-relative" href="<?= SITE_URL ?>/cart.php">
                            <i class="fas fa-shopping-cart fa-lg"></i>
                            <?php 
                            $cartCount = getCartCount(); 
                            $cartTotal = calculateCartTotal();
                            ?>
                            <?php if ($cartCount > 0): ?>
                                <span class="cart-badge badge bg-danger"><?= $cartCount ?></span>
                                <div class="cart-total-badge" style="font-size: 0.7rem; color: var(--primary-color); font-weight: 600; text-align: center; margin-top: 2px;">
                                    <?= formatPrice($cartTotal) ?>
                                </div>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <!-- Авторизация пользователя -->
                    <?php if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in']): ?>
                        <li class="nav-item dropdown ms-lg-2">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?= escape($_SESSION['user_name'] ?? 'Пользователь') ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?= SITE_URL ?>/profile.php">
                                    <i class="fas fa-user-circle me-2"></i>Мой профиль
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= SITE_URL ?>/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Выйти
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item ms-lg-2">
                            <a class="nav-link" href="<?= SITE_URL ?>/login.php">
                                <i class="fas fa-sign-in-alt me-1"></i>Вход
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-sm btn-primary ms-2" href="<?= SITE_URL ?>/register.php">
                                Регистрация
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']): ?>
                        <li class="nav-item ms-lg-2">
                            <a class="nav-link" href="<?= SITE_URL ?>/admin/">
                                <i class="fas fa-cog"></i> Админка
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Flash сообщения -->
    <?php $flash = getFlashMessage(); ?>
    <?php if ($flash): ?>
        <div class="container mt-3">
            <div class="alert alert-<?= escape($flash['type']) ?> alert-dismissible fade show" role="alert">
                <?= escape($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- CSRF токен для AJAX запросов -->
    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>" id="csrf_token_global">
    
    <!-- Основной контент -->
    <main>
