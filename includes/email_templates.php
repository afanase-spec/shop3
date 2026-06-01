<?php
/**
 * HTML шаблоны email-писем.
 * Все стили инлайновые — почтовые клиенты (Gmail, Outlook) не подгружают внешний CSS.
 */

/**
 * Форматирует цену для письма (без зависимости от functions.php)
 */
function emailFormatPrice($price): string {
    return number_format((float)$price, 2, ',', ' ') . ' ₽';
}

/**
 * Эскейпит строку для HTML
 */
function emailEscape($str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Шаблон письма "Заказ создан"
 *
 * @param array $order    запись из orders
 * @param array $items    массив позиций с product_name, product_image, quantity, price_at_time
 * @param string|null $unsubscribeToken  токен отписки (если есть user_id)
 * @return string HTML
 */
function renderOrderCreatedEmail(array $order, array $items, ?string $unsubscribeToken = null): string {
    $orderId      = (int)$order['id'];
    $customerName = emailEscape($order['customer_name']);
    $phone        = emailEscape($order['phone']);
    $address      = emailEscape($order['address']);
    $comment      = !empty($order['comment']) ? emailEscape($order['comment']) : '';
    $total        = emailFormatPrice($order['total']);
    $createdAt    = date('d.m.Y H:i', strtotime($order['created_at']));

    $siteUrl  = SITE_URL;
    $siteName = emailEscape(SITE_NAME);

    // Кнопка "посмотреть заказ" — если есть user_id, шлём в профиль; иначе на главную
    $orderLinkUrl = !empty($order['user_id']) 
        ? $siteUrl . '/profile.php' 
        : $siteUrl . '/';
    $orderLinkText = !empty($order['user_id']) 
        ? 'Посмотреть в личном кабинете' 
        : 'Перейти на сайт';

    // Ссылка отписки
    $unsubscribeLink = '';
    if ($unsubscribeToken) {
        $url = $siteUrl . '/unsubscribe.php?token=' . urlencode($unsubscribeToken);
        $unsubscribeLink = '
            <p style="margin: 16px 0 0 0; font-size: 12px; color: #999999; text-align: center;">
                Не хотите получать письма? 
                <a href="' . emailEscape($url) . '" style="color: #999999; text-decoration: underline;">Отписаться</a>
            </p>';
    }

    // Строки товаров
    $itemsHtml = '';
    foreach ($items as $item) {
        $name     = emailEscape($item['product_name']);
        $qty      = (int)$item['quantity'];
        $price    = emailFormatPrice($item['price_at_time']);
        $subtotal = emailFormatPrice($item['price_at_time'] * $item['quantity']);
        $img      = !empty($item['product_image']) ? emailEscape($item['product_image']) : '';

        $imageCell = $img 
            ? '<img src="' . $img . '" alt="" width="56" height="56" style="display:block; border-radius:8px; object-fit:cover;">'
            : '<div style="width:56px; height:56px; background:#f0f0f0; border-radius:8px;"></div>';

        $itemsHtml .= '
        <tr>
            <td style="padding: 12px 0; border-bottom: 1px solid #f0f0f0; vertical-align: middle;" width="72">
                ' . $imageCell . '
            </td>
            <td style="padding: 12px 8px; border-bottom: 1px solid #f0f0f0; vertical-align: middle;">
                <div style="font-weight: 600; color: #2c3e50; font-size: 14px;">' . $name . '</div>
                <div style="font-size: 13px; color: #95a5a6; margin-top: 4px;">' . $price . ' × ' . $qty . ' шт.</div>
            </td>
            <td style="padding: 12px 0; border-bottom: 1px solid #f0f0f0; vertical-align: middle; text-align: right; font-weight: 700; color: #2c3e50; font-size: 15px;" width="100">
                ' . $subtotal . '
            </td>
        </tr>';
    }

    $commentBlock = '';
    if (!empty($comment)) {
        $commentBlock = '
        <tr>
            <td style="padding: 8px 24px;">
                <div style="font-size: 13px; color: #95a5a6; margin-bottom: 4px;">💬 Комментарий к заказу:</div>
                <div style="font-size: 14px; color: #2c3e50;">' . $comment . '</div>
            </td>
        </tr>';
    }

    // ============================
    // HTML письма
    // ============================
    return '<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Заказ принят</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f5f7fa; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f5f7fa; padding: 32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.06);">
                
                <!-- Header с градиентом -->
                <tr>
                    <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 24px; text-align: center;">
                        <div style="font-size: 56px; line-height: 1; margin-bottom: 12px;">✅</div>
                        <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700; letter-spacing: -0.5px;">
                            Заказ принят!
                        </h1>
                        <p style="margin: 8px 0 0 0; color: rgba(255,255,255,0.9); font-size: 16px;">
                            Спасибо за покупку в ' . $siteName . '
                        </p>
                    </td>
                </tr>
                
                <!-- Основная информация -->
                <tr>
                    <td style="padding: 32px 32px 16px 32px;">
                        <p style="margin: 0 0 16px 0; font-size: 16px; color: #2c3e50;">
                            Здравствуйте, <strong>' . $customerName . '</strong>!
                        </p>
                        <p style="margin: 0 0 24px 0; font-size: 15px; color: #5a6c7d; line-height: 1.6;">
                            Мы получили ваш заказ и уже начали его обрабатывать. В ближайшее время с вами свяжется наш менеджер для подтверждения деталей доставки.
                        </p>
                        
                        <!-- Номер заказа -->
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f8f9fa; border-radius: 12px; margin-bottom: 24px;">
                            <tr>
                                <td style="padding: 20px 24px;">
                                    <div style="font-size: 13px; color: #95a5a6; margin-bottom: 4px;">Номер заказа</div>
                                    <div style="font-size: 24px; font-weight: 700; color: #2c3e50;">#' . $orderId . '</div>
                                    <div style="font-size: 13px; color: #95a5a6; margin-top: 8px;">от ' . $createdAt . '</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                
                <!-- Состав заказа -->
                <tr>
                    <td style="padding: 0 32px;">
                        <h3 style="margin: 0 0 16px 0; color: #2c3e50; font-size: 18px; font-weight: 700;">
                            📦 Состав заказа
                        </h3>
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            ' . $itemsHtml . '
                        </table>
                    </td>
                </tr>
                
                <!-- Итого -->
                <tr>
                    <td style="padding: 16px 32px 24px 32px;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #e8f5e9; border-radius: 12px;">
                            <tr>
                                <td style="padding: 20px 24px;">
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                        <tr>
                                            <td style="font-size: 16px; color: #2c3e50; font-weight: 600;">Итого к оплате:</td>
                                            <td style="font-size: 24px; color: #27ae60; font-weight: 700; text-align: right;">' . $total . '</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                
                <!-- Доставка -->
                <tr>
                    <td style="padding: 0 32px 24px 32px;">
                        <h3 style="margin: 0 0 16px 0; color: #2c3e50; font-size: 18px; font-weight: 700;">
                            🚚 Доставка
                        </h3>
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f8f9fa; border-radius: 12px;">
                            <tr>
                                <td style="padding: 16px 24px;">
                                    <div style="font-size: 13px; color: #95a5a6; margin-bottom: 4px;">📍 Адрес:</div>
                                    <div style="font-size: 14px; color: #2c3e50; margin-bottom: 12px;">' . $address . '</div>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 0 24px 16px 24px;">
                                    <div style="font-size: 13px; color: #95a5a6; margin-bottom: 4px;">📞 Телефон:</div>
                                    <div style="font-size: 14px; color: #2c3e50;">' . $phone . '</div>
                                </td>
                            </tr>
                            ' . $commentBlock . '
                        </table>
                    </td>
                </tr>
                
                <!-- Кнопка -->
                <tr>
                    <td style="padding: 0 32px 32px 32px; text-align: center;">
                        <a href="' . emailEscape($orderLinkUrl) . '" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 12px; font-weight: 600; font-size: 15px;">
                            ' . $orderLinkText . '
                        </a>
                    </td>
                </tr>
                
                <!-- Footer -->
                <tr>
                    <td style="background-color: #2c3e50; padding: 24px 32px; text-align: center;">
                        <p style="margin: 0 0 8px 0; color: #ffffff; font-size: 16px; font-weight: 600;">
                            ' . $siteName . '
                        </p>
                        <p style="margin: 0; color: rgba(255,255,255,0.7); font-size: 13px;">
                            Это автоматическое письмо. Если у вас есть вопросы — свяжитесь с нами.
                        </p>
                        ' . $unsubscribeLink . '
                    </td>
                </tr>
                
            </table>
        </td>
    </tr>
</table>

</body>
</html>';
}