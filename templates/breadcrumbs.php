<?php
/**
 * Универсальный шаблон хлебных крошек.
 * 
 * Использование:
 *   $breadcrumbs = [
 *       ['Каталог', '/catalog.php'],
 *       ['Молочные продукты', '/catalog.php?category=2'],
 *       ['Молоко "Простоквашино"', null]  // null = последний пункт, не ссылка
 *   ];
 *   include __DIR__ . '/templates/breadcrumbs.php';
 * 
 * Главная добавляется автоматически как первый элемент.
 */

if (!isset($breadcrumbs) || !is_array($breadcrumbs)) {
    return;
}
?>

<nav class="breadcrumbs-nav" aria-label="Хлебные крошки">
    <div class="container">
        <ol class="breadcrumbs-list">
            <li class="breadcrumbs-item">
                <a href="<?= SITE_URL ?>/" class="breadcrumbs-link">
                    <i class="fas fa-home"></i>
                    <span>Главная</span>
                </a>
            </li>
            
            <?php foreach ($breadcrumbs as $index => $crumb): 
                $label = $crumb[0] ?? '';
                $url = $crumb[1] ?? null;
                $isLast = ($index === count($breadcrumbs) - 1);
            ?>
                <li class="breadcrumbs-separator" aria-hidden="true">
                    <i class="fas fa-chevron-right"></i>
                </li>
                
                <li class="breadcrumbs-item<?= $isLast ? ' breadcrumbs-item-active' : '' ?>">
                    <?php if ($url && !$isLast): ?>
                        <a href="<?= SITE_URL . escape($url) ?>" class="breadcrumbs-link">
                            <?= escape($label) ?>
                        </a>
                    <?php else: ?>
                        <span class="breadcrumbs-current" aria-current="page">
                            <?= escape($label) ?>
                        </span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</nav>

<?php unset($breadcrumbs); ?>