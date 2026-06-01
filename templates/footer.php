</main>
    
    <!-- Футер -->
    <footer class="site-footer mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5 class="fw-bold mb-3 footer-brand">
                        <i class="fas fa-shopping-basket me-2"></i><?= SITE_NAME ?>
                    </h5>
                    <p class="footer-text">Быстрая доставка продуктов и товаров для дома прямо к вашей двери.</p>
                </div>
                
                <div class="col-md-4 mb-3">
                    <h6 class="fw-bold mb-3 footer-heading">Контакты</h6>
                    <ul class="list-unstyled footer-text">
                        <li class="mb-2"><i class="fas fa-phone me-2 footer-icon"></i>+7 (999) 665 67 42</li>
                        <li class="mb-2"><i class="fas fa-envelope me-2 footer-icon"></i>info@delivery.ru</li>
                        <li><i class="fas fa-map-marker-alt me-2 footer-icon"></i>г. Чебоксары, Декабристов, 17</li>
                    </ul>
                </div>
                
                <div class="col-md-4 mb-3">
                    <h6 class="fw-bold mb-3 footer-heading">Мы в соцсетях</h6>
                    <div class="social-links">
                        <a href="#" class="footer-social"><i class="fab fa-vk fa-lg"></i></a>
                        <a href="#" class="footer-social"><i class="fab fa-telegram fa-lg"></i></a>
                        <a href="#" class="footer-social"><i class="fab fa-whatsapp fa-lg"></i></a>
                    </div>
                </div>
            </div>
            
            <hr class="footer-divider my-3">
            
            <div class="text-center footer-text">
                <small>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. Все права защищены.</small>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Передаём SITE_URL в JS -->
    <script>
        window.SITE_URL = <?= json_encode(SITE_URL) ?>;
    </script>
    <!-- Custom JS -->
    <script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>