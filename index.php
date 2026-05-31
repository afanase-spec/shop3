<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Главная - ' . SITE_NAME;

include __DIR__ . '/templates/header.php';
?>

<!-- Hero секция -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 animate-fade-in">
                <h1>Быстрая доставка продуктов</h1>
                <p>Свежие продукты и товары для дома с доставкой прямо к вашей двери. Экономьте время и деньги!</p>
                <a href="<?= SITE_URL ?>/catalog.php" class="btn btn-light btn-lg">
                    <i class="fas fa-shopping-bag me-2"></i>Перейти в каталог
                </a>
            </div>
            <div class="col-lg-6 text-center d-none d-lg-block">
                <i class="fas fa-shopping-basket" style="font-size: 15rem; opacity: 0.2;"></i>
            </div>
        </div>
    </div>
</section>

<!-- О нашей компании -->
<section class="container mb-5">
    <h2 class="section-title">Почему выбирают нас?</h2>
    
    <div class="row g-4 mb-5">
        <!-- Быстрая доставка -->
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center h-100 animate-fade-in">
                <div class="mb-3">
                    <i class="fas fa-shipping-fast fa-3x" style="color: var(--primary-color);"></i>
                </div>
                <h4 class="fw-bold mb-3">Быстрая доставка</h4>
                <p class="text-muted mb-0">Доставляем заказы в течение 1-2 часов по всему городу. Работаем ежедневно с 9:00 до 21:00</p>
            </div>
        </div>
        
        <!-- Свежие продукты -->
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center h-100 animate-fade-in">
                <div class="mb-3">
                    <i class="fas fa-leaf fa-3x" style="color: var(--primary-color);"></i>
                </div>
                <h4 class="fw-bold mb-3">Свежие продукты</h4>
                <p class="text-muted mb-0">Только свежие и качественные продукты от проверенных поставщиков. Гарантия качества</p>
            </div>
        </div>
        
        <!-- Лучшие цены -->
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center h-100 animate-fade-in">
                <div class="mb-3">
                    <i class="fas fa-tags fa-3x" style="color: var(--primary-color);"></i>
                </div>
                <h4 class="fw-bold mb-3">Лучшие цены</h4>
                <p class="text-muted mb-0">Конкурентные цены и регулярные акции. Бесплатная доставка при заказе от 2000 ₽</p>
            </div>
        </div>
        
        <!-- Поддержка 24/7 -->
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center h-100 animate-fade-in">
                <div class="mb-3">
                    <i class="fas fa-headset fa-3x" style="color: var(--primary-color);"></i>
                </div>
                <h4 class="fw-bold mb-3">Поддержка 24/7</h4>
                <p class="text-muted mb-0">Наша служба поддержки всегда на связи. Ответим на любые вопросы и поможем с заказом</p>
            </div>
        </div>
    </div>

</section>

<!-- Как это работает -->
<section class="container mb-5">
    <h2 class="section-title">Как сделать заказ?</h2>
    
    <div class="row g-4">
        <div class="col-md-4">
            <div class="text-center animate-fade-in">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                     style="width: 80px; height: 80px; background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white; font-size: 2rem; font-weight: bold;">1</div>
                <h4 class="fw-bold">Выберите товары</h4>
                <p class="text-muted">Добавьте нужные товары в корзину из нашего каталога</p>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="text-center animate-fade-in">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                     style="width: 80px; height: 80px; background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white; font-size: 2rem; font-weight: bold;">2</div>
                <h4 class="fw-bold">Оформите заказ</h4>
                <p class="text-muted">Заполните данные для доставки и подтвердите заказ</p>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="text-center animate-fade-in">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                     style="width: 80px; height: 80px; background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white; font-size: 2rem; font-weight: bold;">3</div>
                <h4 class="fw-bold">Получите доставку</h4>
                <p class="text-muted">Курьер доставит ваш заказ прямо к двери в удобное время</p>
            </div>
        </div>
    </div>
    
    <div class="text-center mt-5">
        <a href="<?= SITE_URL ?>/catalog.php" class="btn btn-primary btn-lg px-5">
            <i class="fas fa-shopping-bag me-2"></i>Начать покупки
        </a>
    </div>
</section>

<?php include __DIR__ . '/templates/footer.php'; ?>
