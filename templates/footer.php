    </main>
    
    <!-- Футер -->
    <footer class="bg-dark text-white mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-shopping-basket me-2"></i><?= SITE_NAME ?>
                    </h5>
                    <p style="color: #b0b0b0;">Быстрая доставка продуктов и товаров для дома прямо к вашей двери.</p>
                </div>
                
                <div class="col-md-4 mb-3">
                    <h6 class="fw-bold mb-3">Контакты</h6>
                    <ul class="list-unstyled" style="color: #b0b0b0;">
                        <li class="mb-2"><i class="fas fa-phone me-2"></i>+7 (999) 665 67 42</li>
                        <li class="mb-2"><i class="fas fa-envelope me-2"></i>info@delivery.ru</li>
                        <li><i class="fas fa-map-marker-alt me-2"></i>г. Чебоксары, Декабристов, 17</li>
                    </ul>
                </div>
                
                <div class="col-md-4 mb-3">
                    <h6 class="fw-bold mb-3">Мы в соцсетях</h6>
                    <div class="social-links">
                        <a href="#" class="text-white me-3"><i class="fab fa-vk fa-lg"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-telegram fa-lg"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-whatsapp fa-lg"></i></a>
                    </div>
                </div>
            </div>
            
            <hr class="my-3" style="border-color: rgba(255,255,255,0.1);">
            
            <div class="text-center" style="color: #b0b0b0;">
                <small>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. Все права защищены.</small>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
