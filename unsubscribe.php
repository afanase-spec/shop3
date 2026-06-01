<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Отписка от уведомлений - ' . SITE_NAME;
$success = false;
$alreadyUnsubscribed = false;
$invalidToken = false;
$user = null;

$token = trim($_GET['token'] ?? '');

if (empty($token) || !preg_match('/^[a-f0-9]{32,64}$/i', $token)) {
    $invalidToken = true;
} else {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, email, name, email_notifications FROM users WHERE unsubscribe_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $invalidToken = true;
    } elseif (intval($user['email_notifications']) === 0) {
        $alreadyUnsubscribed = true;
    } else {
        // Отписываем
        $stmt = $db->prepare("UPDATE users SET email_notifications = 0 WHERE id = ?");
        $stmt->execute([$user['id']]);
        $success = true;
    }
}

include __DIR__ . '/templates/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
                
                <?php if ($invalidToken): ?>
                    <i class="fas fa-times-circle text-danger mb-4" style="font-size: 4rem;"></i>
                    <h2 class="fw-bold mb-3">Недействительная ссылка</h2>
                    <p class="text-muted mb-4">
                        Эта ссылка отписки устарела или некорректна. 
                        Управлять уведомлениями можно в личном кабинете.
                    </p>
                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                        <a href="<?= SITE_URL ?>/login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>Войти в аккаунт
                        </a>
                        <a href="<?= SITE_URL ?>/" class="btn btn-outline-secondary">
                            <i class="fas fa-home me-2"></i>На главную
                        </a>
                    </div>
                
                <?php elseif ($alreadyUnsubscribed): ?>
                    <i class="fas fa-info-circle text-info mb-4" style="font-size: 4rem;"></i>
                    <h2 class="fw-bold mb-3">Вы уже отписаны</h2>
                    <p class="text-muted mb-4">
                        Уведомления для <strong><?= escape($user['email']) ?></strong> уже отключены.
                    </p>
                    <p class="text-muted mb-4">
                        Хотите снова получать письма? Включите их в личном кабинете.
                    </p>
                    <a href="<?= SITE_URL ?>/profile.php" class="btn btn-primary">
                        <i class="fas fa-user me-2"></i>Перейти в профиль
                    </a>
                
                <?php elseif ($success): ?>
                    <i class="fas fa-check-circle text-success mb-4" style="font-size: 4rem;"></i>
                    <h2 class="fw-bold mb-3">Вы отписались</h2>
                    <p class="text-muted mb-2">
                        Здравствуйте, <strong><?= escape($user['name']) ?></strong>!
                    </p>
                    <p class="text-muted mb-4">
                        Уведомления для <strong><?= escape($user['email']) ?></strong> отключены. 
                        Больше мы не будем отправлять вам письма о заказах.
                    </p>
                    <div class="alert alert-light border">
                        <i class="fas fa-undo me-2"></i>
                        Передумали? Вы всегда можете снова включить уведомления в личном кабинете.
                    </div>
                    <a href="<?= SITE_URL ?>/profile.php" class="btn btn-primary mt-3">
                        <i class="fas fa-user me-2"></i>Перейти в профиль
                    </a>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/templates/footer.php'; ?>