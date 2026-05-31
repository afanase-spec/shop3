<?php
require_once __DIR__ . '/includes/functions.php';

// Проверка авторизации
if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
    setFlashMessage('Для доступа к профилю необходимо войти', 'warning');
    redirect('/login.php');
}

$pageTitle = 'Мой профиль - ' . SITE_NAME;
$db = getDB();
$message = '';
$error = '';

// Получаем данные пользователя
$userId = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    redirect('/login.php');
}

// Обработка обновления профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности';
    } else {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        
        if (empty($name)) {
            $error = 'Введите имя';
        } else {
            $stmt = $db->prepare("UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $address, $userId]);
            
            // Обновляем сессию
            $_SESSION['user_name'] = $name;
            
            $message = 'Профиль успешно обновлен';
        }
    }
}

// Обработка смены пароля
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'Заполните все поля';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Новые пароли не совпадают';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Пароль должен быть не менее 6 символов';
        } else {
            // Проверяем текущий пароль
            if (!password_verify($currentPassword, $user['password_hash'])) {
                $error = 'Неверный текущий пароль';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$newHash, $userId]);
                
                $message = 'Пароль успешно изменен';
            }
        }
    }
}

// Получаем историю заказов пользователя
$stmt = $db->prepare("SELECT * FROM orders WHERE customer_name = ? OR phone = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$user['name'], $user['phone']]);
$orders = $stmt->fetchAll();

// Подсчитываем общую потраченную сумму
$totalSpent = 0;
foreach ($orders as $order) {
    $totalSpent += floatval($order['total']);
}

include __DIR__ . '/templates/header.php';
?>

<div class="container my-5">
    <h1 class="section-title">Мой профиль</h1>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?= escape($message) ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= escape($error) ?></div>
    <?php endif; ?>
    
    <div class="row g-4">
        <!-- Основная информация -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <h3 class="fw-bold mb-4"><i class="fas fa-user-circle me-2 text-primary"></i>Личные данные</h3>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" class="form-control" value="<?= escape($user['email']) ?>" disabled>
                            <small class="text-muted">Email нельзя изменить</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Имя <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control" 
                                   name="name" 
                                   required 
                                   value="<?= escape($user['name']) ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Телефон</label>
                            <input type="tel" 
                                   class="form-control" 
                                   name="phone"
                                   value="<?= escape($user['phone'] ?? '') ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Дата регистрации</label>
                            <input type="text" 
                                   class="form-control" 
                                   value="<?= date('d.m.Y', strtotime($user['created_at'])) ?>" 
                                   disabled>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-semibold">Адрес доставки</label>
                            <textarea class="form-control" 
                                      name="address" 
                                      rows="3"
                                      placeholder="Введите ваш адрес для быстрой доставки"><?= escape($user['address'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary mt-4">
                        <i class="fas fa-save me-2"></i>Сохранить изменения
                    </button>
                </form>
            </div>
            
            <!-- Смена пароля -->
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <h3 class="fw-bold mb-4"><i class="fas fa-lock me-2 text-primary"></i>Смена пароля</h3>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Текущий пароль</label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Новый пароль</label>
                        <input type="password" 
                               class="form-control" 
                               name="new_password" 
                               required 
                               minlength="6">
                        <small class="text-muted">Минимум 6 символов</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Подтвердите новый пароль</label>
                        <input type="password" 
                               class="form-control" 
                               name="confirm_password" 
                               required 
                               minlength="6">
                    </div>
                    
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-key me-2"></i>Изменить пароль
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Боковая панель -->
        <div class="col-lg-4">
            <!-- Статистика -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <h4 class="fw-bold mb-3">Статистика</h4>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Всего заказов:</span>
                    <strong><?= count($orders) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Потрачено:</span>
                    <strong class="text-success"><?= formatPrice($totalSpent) ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Дата регистрации:</span>
                    <strong><?= date('d.m.Y', strtotime($user['created_at'])) ?></strong>
                </div>
            </div>
        </div>
    </div>
    
    <!-- История заказов -->
    <?php if (!empty($orders)): ?>
        <div class="card border-0 shadow-sm rounded-4 mt-4">
            <div class="card-header bg-white border-0 py-3">
                <h3 class="fw-bold mb-0"><i class="fas fa-history me-2 text-primary"></i>История заказов</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>№ заказа</th>
                                <th>Дата</th>
                                <th>Сумма</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><strong>#<?= $order['id'] ?></strong></td>
                                    <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                                    <td><strong><?= formatPrice($order['total']) ?></strong></td>
                                    <td>
                                        <?php
                                        $statusClasses = [
                                            'new' => 'bg-primary',
                                            'processing' => 'bg-warning',
                                            'delivered' => 'bg-success'
                                        ];
                                        $statusNames = [
                                            'new' => 'Новый',
                                            'processing' => 'В обработке',
                                            'delivered' => 'Доставлен'
                                        ];
                                        ?>
                                        <span class="badge <?= $statusClasses[$order['status']] ?>">
                                            <?= $statusNames[$order['status']] ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/templates/footer.php'; ?>
