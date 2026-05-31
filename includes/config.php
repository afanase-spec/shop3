<?php
session_start();
date_default_timezone_set('Europe/Moscow');

// Константы БД
define('DB_HOST', 'localhost');
define('DB_NAME', 'delivery');
define('DB_USER', 'root');
define('DB_PASS', '');

// Константы сайта
define('SITE_URL', 'http://shop2');
define('SITE_NAME', 'Доставка Продуктов');

// Настройки безопасности
define('CSRF_TOKEN_NAME', 'csrf_token');
