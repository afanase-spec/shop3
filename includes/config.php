<?php
session_start();
date_default_timezone_set('Europe/Moscow');

// Константы БД
define('DB_HOST', 'localhost');
define('DB_NAME', 'delivery');
define('DB_USER', 'root');
define('DB_PASS', '');

// Константы сайта
define('SITE_URL', 'http://shop3');
define('SITE_NAME', 'Доставка Продуктов');

// Настройки безопасности
define('CSRF_TOKEN_NAME', 'csrf_token');

// ====================================
// EMAIL НАСТРОЙКИ (SMTP Gmail)
// ====================================

// Включить отправку через SMTP. Если false или пустые SMTP_USER/SMTP_PASS — письма пишутся в logs/emails/
define('EMAIL_ENABLED', true);

// SMTP сервер
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls'); // tls для 587, ssl для 465

// 👇 ВПИШИ СЮДА СВОИ ДАННЫЕ
define('SMTP_USER', 'afanasevki6@gmail.com');     // <-- замени на свой gmail
define('SMTP_PASS', 'tvbbbsluiqsqespm');          // <-- замени на 16-значный App Password (без пробелов!)

// От кого приходят письма
define('MAIL_FROM_EMAIL', SMTP_USER);
define('MAIL_FROM_NAME', SITE_NAME);

// Email админа для отчётов (необязательно, можно тот же что и SMTP_USER)
define('ADMIN_EMAIL', SMTP_USER);

// Путь к логам писем (если SMTP недоступен)
define('EMAIL_LOG_DIR', __DIR__ . '/../logs/emails/');