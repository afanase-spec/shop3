<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Контакты - ' . SITE_NAME;

include __DIR__ . '/templates/header.php';
?>

<?php $breadcrumbs = [['Контакты', null]]; include __DIR__ . '/templates/breadcrumbs.php'; ?>

<div class="container my-5">
    <h1 class="section-title">Контакты</h1>
    
    <div class="row g-4 mb-5">
        <!-- Информация о доставке -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <h3 class="fw-bold mb-4"><i class="fas fa-truck text-primary me-2"></i>Информация о доставке</h3>
                
                <div class="mb-4">
                    <h5 class="fw-semibold">Время доставки</h5>
                    <p class="text-muted">Ежедневно с 9:00 до 21:00</p>
                </div>
                
                <div class="mb-4">
                    <h5 class="fw-semibold">Стоимость доставки</h5>
                    <ul class="list-unstyled text-muted">
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Бесплатно при заказе от 2000 ₽</li>
                        <li><i class="fas fa-check text-success me-2"></i>200 ₽ при заказе менее 2000 ₽</li>
                    </ul>
                </div>
                
                <div class="mb-4">
                    <h5 class="fw-semibold">Зоны доставки</h5>
                    <p class="text-muted">Доставляем по всей Москве и Московской области в пределах 30 км от МКАД</p>
                </div>
                
                <div>
                    <h5 class="fw-semibold">Способы оплаты</h5>
                    <ul class="list-unstyled text-muted">
                        <li class="mb-2"><i class="fas fa-money-bill-wave text-success me-2"></i>Наличными курьеру</li>
                        <li><i class="fas fa-credit-card text-success me-2"></i>Банковской картой курьеру</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Контактные данные -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <h3 class="fw-bold mb-4"><i class="fas fa-address-book text-primary me-2"></i>Наши контакты</h3>
                
                <div class="mb-4">
                    <div class="d-flex align-items-start mb-3">
                        <i class="fas fa-phone fa-lg text-primary mt-1 me-3"></i>
                        <div>
                            <h6 class="fw-semibold mb-1">Телефон</h6>
                            <p class="text-muted mb-0">+7 (999) 123-45-67</p>
                            <small class="text-muted">Ежедневно с 9:00 до 21:00</small>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-start mb-3">
                        <i class="fas fa-envelope fa-lg text-primary mt-1 me-3"></i>
                        <div>
                            <h6 class="fw-semibold mb-1">Email</h6>
                            <p class="text-muted mb-0">info@delivery.ru</p>
                            <small class="text-muted">Ответим в течение 24 часов</small>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-start">
                        <i class="fas fa-map-marker-alt fa-lg text-primary mt-1 me-3"></i>
                        <div>
                            <h6 class="fw-semibold mb-1">Адрес</h6>
                            <p class="text-muted mb-0">г. Москва, ул. Примерная, 1</p>
                            <small class="text-muted">Офис и склад</small>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div>
                    <h6 class="fw-semibold mb-3">Мы в социальных сетях</h6>
                    <div class="social-links">
                        <a href="#" class="btn btn-outline-primary me-2 mb-2">
                            <i class="fab fa-vk me-2"></i>ВКонтакте
                        </a>
                        <a href="#" class="btn btn-outline-info me-2 mb-2">
                            <i class="fab fa-telegram me-2"></i>Telegram
                        </a>
                        <a href="#" class="btn btn-outline-success mb-2">
                            <i class="fab fa-whatsapp me-2"></i>WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Карта -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <iframe 
            src="https://yandex.ru/map-widget/v1/?um=constructor%3A0&amp;source=constructor" 
            width="100%" 
            height="400" 
            frameborder="0"
            style="border:0;">
        </iframe>
    </div>
</div>

<?php include __DIR__ . '/templates/footer.php'; ?>
