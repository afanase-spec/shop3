<?php
/**
 * Карточка-скелетон товара.
 * Используется как плейсхолдер пока грузятся реальные карточки.
 * 
 * Пример использования:
 *   <div class="row g-4">
 *       <?php for ($i = 0; $i < 9; $i++): ?>
 *           <div class="col-md-6 col-xl-4">
 *               <?php include __DIR__ . '/skeleton-card.php'; ?>
 *           </div>
 *       <?php endfor; ?>
 *   </div>
 */
?>
<div class="product-skeleton-card">
    <div class="skeleton skeleton-image"></div>
    <div class="skeleton skeleton-title"></div>
    <div class="skeleton skeleton-text" style="width: 60%;"></div>
    <div class="skeleton skeleton-button"></div>
</div>