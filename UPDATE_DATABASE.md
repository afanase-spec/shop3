# Инструкция по обновлению базы данных

Если вы уже импортировали install.sql ранее, нужно добавить только таблицу users.

## Способ 1: Через phpMyAdmin

1. Откройте phpMyAdmin
2. Выберите базу данных `delivery`
3. Перейдите на вкладку "SQL"
4. Выполните следующий запрос:

```sql
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Способ 2: Повторный импорт

1. Удалите базу данных `delivery` в phpMyAdmin
2. Создайте новую базу данных `delivery` с кодировкой `utf8mb4_unicode_ci`
3. Импортируйте файл `install.sql` заново

**ВНИМАНИЕ:** Этот способ удалит все существующие данные!

---

После обновления базы данных будут доступны функции:
- Регистрация новых пользователей
- Авторизация пользователей
- Сохранение информации о пользователе в сессии

URL для регистрации: http://shop2/register.php  
URL для входа: http://shop2/login.php
