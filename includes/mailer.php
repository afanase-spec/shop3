<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/email_templates.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Проверяет, заполнены ли SMTP-настройки
 */
function isSmtpConfigured(): bool {
    if (!defined('EMAIL_ENABLED') || !EMAIL_ENABLED) {
        return false;
    }
    if (!defined('SMTP_USER') || !defined('SMTP_PASS')) {
        return false;
    }
    $user = SMTP_USER;
    $pass = SMTP_PASS;
    // Проверяем что это не дефолтные значения-заглушки
    if (empty($user) || empty($pass)) {
        return false;
    }
    if (strpos($user, 'твой_gmail') !== false || $user === 'your_gmail@gmail.com') {
        return false;
    }
    if ($pass === 'abcdefghijklmnop' || strlen($pass) < 10) {
        return false;
    }
    return true;
}

/**
 * Отправляет письмо через SMTP или пишет в файл-лог.
 * Записывает результат в email_log.
 *
 * @param string $toEmail   получатель
 * @param string $toName    имя получателя
 * @param string $subject   тема
 * @param string $htmlBody  HTML-тело письма
 * @param string $eventType тип события (для лога): order_created, etc.
 * @param int|null $orderId связанный заказ
 * @param int|null $userId  связанный пользователь
 * @return bool true если успех (отправлено или залогировано)
 */
function sendEmail(
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody,
    string $eventType,
    ?int $orderId = null,
    ?int $userId = null
): bool {
    $db = getDB();
    $status = 'sent';
    $transport = 'smtp';
    $errorMessage = null;

    // ============================================
    // Режим 1: SMTP настроен — пробуем отправить
    // ============================================
    if (isSmtpConfigured()) {
        $mail = new PHPMailer(true);
        try {
            // Настройки сервера
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';
            $mail->Encoding   = 'base64';

            // Отправитель/получатель
            $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            $mail->addAddress($toEmail, $toName);
            $mail->addReplyTo(MAIL_FROM_EMAIL, MAIL_FROM_NAME);

            // Содержимое
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags(preg_replace('/<style[^>]*>.*?<\/style>/si', '', $htmlBody));

            $mail->send();
            $status = 'sent';
            $transport = 'smtp';

        } catch (PHPMailerException $e) {
            // SMTP упал — fallback в лог-файл
            error_log("PHPMailer error: " . $mail->ErrorInfo);
            $errorMessage = $mail->ErrorInfo;
            
            $logged = writeEmailToFile($toEmail, $subject, $htmlBody);
            if ($logged) {
                $status = 'logged';
                $transport = 'file_log';
                $errorMessage = 'SMTP failed: ' . $mail->ErrorInfo . ' | Saved to file';
            } else {
                $status = 'failed';
                $transport = 'smtp';
            }
        }
    } 
    // ============================================
    // Режим 2: SMTP не настроен — пишем в файл
    // ============================================
    else {
        $logged = writeEmailToFile($toEmail, $subject, $htmlBody);
        $status = $logged ? 'logged' : 'failed';
        $transport = 'file_log';
        if (!$logged) {
            $errorMessage = 'Не удалось записать письмо в файл';
        }
    }

    // ============================================
    // Запись в БД (email_log)
    // ============================================
    try {
        $stmt = $db->prepare("
            INSERT INTO email_log 
                (order_id, user_id, recipient, subject, event_type, status, transport, error_message) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $orderId,
            $userId,
            $toEmail,
            $subject,
            $eventType,
            $status,
            $transport,
            $errorMessage
        ]);
    } catch (Exception $e) {
        error_log("Не удалось записать email_log: " . $e->getMessage());
    }

    return ($status === 'sent' || $status === 'logged');
}

/**
 * Пишет письмо в файл logs/emails/YYYY-MM-DD_HHMMSS_recipient.html
 * Открываешь в браузере — видишь как выглядит.
 */
function writeEmailToFile(string $toEmail, string $subject, string $htmlBody): bool {
    $dir = EMAIL_LOG_DIR;

    // Создаём папку если нет
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            error_log("Не удалось создать папку логов: $dir");
            return false;
        }
    }

    // Защитим от листинга директории
    $htaccess = $dir . '.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Options -Indexes\nDeny from all\n");
    }

    // Имя файла: дата_время_email.html
    $safeEmail = preg_replace('/[^a-z0-9_\-]/i', '_', $toEmail);
    $filename = date('Y-m-d_His') . '_' . $safeEmail . '.html';
    $filepath = $dir . $filename;

    // Добавляем шапку с темой и получателем
    $header  = "<!-- ============================================ -->\n";
    $header .= "<!-- EMAIL LOG (SMTP не настроен или упал)         -->\n";
    $header .= "<!-- Дата: " . date('Y-m-d H:i:s') . "                  -->\n";
    $header .= "<!-- Кому: $toEmail                                -->\n";
    $header .= "<!-- Тема: $subject                                -->\n";
    $header .= "<!-- ============================================ -->\n\n";

    $bytes = @file_put_contents($filepath, $header . $htmlBody);
    return $bytes !== false;
}

/**
 * Helper: отправляет письмо о создании заказа.
 * Если у юзера выключены уведомления (email_notifications = 0) — НЕ шлёт.
 *
 * @param int $orderId  ID заказа из orders
 * @return bool
 */
function sendOrderCreatedEmail(int $orderId): bool {
    $db = getDB();

    // Получаем заказ
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        error_log("sendOrderCreatedEmail: заказ $orderId не найден");
        return false;
    }

    // Email обязателен (политика: только залогиненным шлём)
    if (empty($order['email'])) {
        return false; // тихо пропускаем — гость без email
    }

    // Если есть user_id — проверим что юзер не отписался
    $unsubscribeToken = null;
    if (!empty($order['user_id'])) {
        $stmt = $db->prepare("SELECT email_notifications, unsubscribe_token FROM users WHERE id = ?");
        $stmt->execute([$order['user_id']]);
        $userRow = $stmt->fetch();
        if ($userRow) {
            if (intval($userRow['email_notifications']) === 0) {
                return false; // юзер отписался
            }
            $unsubscribeToken = $userRow['unsubscribe_token'];
        }
    }

    // Получаем позиции заказа
    $stmt = $db->prepare("
        SELECT oi.*, p.name AS product_name, p.image AS product_image
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll();

    // Генерим HTML
    $subject = '🛒 Заказ №' . $order['id'] . ' принят — ' . SITE_NAME;
    $html = renderOrderCreatedEmail($order, $items, $unsubscribeToken);

    return sendEmail(
        $order['email'],
        $order['customer_name'],
        $subject,
        $html,
        'order_created',
        $orderId,
        $order['user_id'] ? intval($order['user_id']) : null
    );
}