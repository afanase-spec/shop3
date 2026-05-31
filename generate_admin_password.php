<?php
// Скрипт для генерации хеша пароля администратора
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Пароль: admin123\n";
echo "Хеш: $hash\n\n";

// Проверка
if (password_verify('admin123', $hash)) {
    echo "✓ Пароль успешно проверен!\n";
} else {
    echo "✗ Ошибка проверки пароля\n";
}

echo "\nSQL запрос для обновления:\n";
echo "UPDATE `admins` SET `password_hash` = '$hash' WHERE `username` = 'admin';\n";
