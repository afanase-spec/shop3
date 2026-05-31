<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#6366f1">
    <title><?= escape($pageTitle ?? SITE_NAME) ?></title>

    <!-- Анти-вспышка: применяем тему ДО загрузки CSS -->
    <script>
        (function() {
            try {
                var saved = localStorage.getItem('theme');
                var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                var theme = saved || (prefersDark ? 'dark' : 'light');
                document.documentElement.setAttribute('data-theme', theme);
            } catch(e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS с версионированием (сбрасывает кэш при изменении файла) -->
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/custom.css?v=<?= @filemtime(__DIR__ . '/../assets/css/custom.css') ?: time() ?>">
</head>
<body>
    <!-- Навбар -->
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold gradient-text" href="<?= SITE_URL ?>/">
                <i class="fas fa-shopping-basket me-2"></i><?= SITE_NAME ?>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
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
                                <span class="cart-badge badge"><?= $cartCount ?></span>
                                <div class="cart-total-badge"><?= formatPrice($cartTotal) ?></div>
                            <?php endif; ?>
                        </a>
                    </li>

                    <?php if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in']): ?>
                        <?php $userName = $_SESSION['user_name'] ?? 'Пользователь'; ?>
                        <li class="nav-item dropdown ms-lg-2">
                            <a class="nav-link dropdown-toggle user-dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                                <span class="user-avatar"><?= mb_strtoupper(mb_substr($userName, 0, 1)) ?></span>
                                <span class="d-none d-md-inline ms-2"><?= escape($userName) ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end user-dropdown">
                                <li><a class="dropdown-item" href="<?= SITE_URL ?>/profile.php">
                                    <i class="fas fa-user-circle me-2"></i>Мой профиль
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button type="button" class="dropdown-item theme-toggle-item" id="themeToggle">
                                        <i class="fas fa-moon me-2 theme-icon-dark"></i>
                                        <i class="fas fa-sun me-2 theme-icon-light"></i>
                                        <span class="theme-label">Тёмная тема</span>
                                        <span class="form-check form-switch ms-auto mb-0">
                                            <input class="form-check-input theme-switch" type="checkbox" role="switch" tabindex="-1" onclick="return false;">
                                        </span>
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="<?= SITE_URL ?>/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Выйти
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item ms-lg-2">
                            <button type="button" class="btn-theme-guest" id="themeToggleGuest" title="Сменить тему" aria-label="Сменить тему">
                                <i class="fas fa-moon theme-icon-dark"></i>
                                <i class="fas fa-sun theme-icon-light"></i>
                            </button>
                        </li>
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
                            <a class="nav-link admin-link" href="<?= SITE_URL ?>/admin/">
                                <i class="fas fa-cog me-1"></i> Админка
                                <span class="admin-badge">ADMIN</span>
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

    <!-- CSRF токен -->
    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>" id="csrf_token_global">

    <!-- Контейнер toast-уведомлений -->
    <div class="toast-container"></div>

    <!-- Основной контент -->
    <main>