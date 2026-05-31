-- База данных для сайта доставки продуктов
-- Создано: 2026-05-27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Создание базы данных
CREATE DATABASE IF NOT EXISTS `delivery` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `delivery`;

-- --------------------------------------------------------
-- Таблица категорий
-- --------------------------------------------------------

CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Таблица товаров
-- --------------------------------------------------------

CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `is_popular` tinyint(1) DEFAULT 0,
  `manufacturer` varchar(200) DEFAULT NULL,
  `composition` text DEFAULT NULL,
  `calories` decimal(6,2) DEFAULT NULL,
  `proteins` decimal(6,2) DEFAULT NULL,
  `fats` decimal(6,2) DEFAULT NULL,
  `carbohydrates` decimal(6,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `category_id` (`category_id`),
  KEY `is_popular` (`is_popular`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Таблица заказов
-- --------------------------------------------------------

CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(200) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `comment` text DEFAULT NULL,
  `status` enum('new','processing','delivered') DEFAULT 'new',
  `total` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Таблица элементов заказа
-- --------------------------------------------------------

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_at_time` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Таблица администраторов
-- --------------------------------------------------------

CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Таблица пользователей
-- --------------------------------------------------------

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

-- --------------------------------------------------------
-- Тестовые данные
-- --------------------------------------------------------

-- Категории
INSERT INTO `categories` (`id`, `name`, `slug`) VALUES
(1, 'Продукты', 'produkty'),
(2, 'Бытовая химия', 'bytovaya-himiya'),
(3, 'Напитки', 'napitki');

-- Товары
INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `description`, `price`, `image`, `is_popular`, `manufacturer`, `composition`, `calories`, `proteins`, `fats`, `carbohydrates`) VALUES
(1, 1, 'Молоко 3.2%', 'moloko-3-2', 'Свежее пастеризованное молоко 3.2% жирности, 1 литр', 89.90, 'https://images.unsplash.com/photo-1563636619-e9143da7973b?w=400', 1, 'Домик в деревне', 'Молоко цельное, молоко нормализованное, витамины A и D', 62.00, 3.20, 3.20, 4.70),
(2, 1, 'Хлеб белый', 'hleb-belyy', 'Нарезной белый хлеб из пшеничной муки высшего сорта, 400г', 45.00, 'https://images.unsplash.com/photo-1598373182133-52452f7691f4?w=400', 1, 'Хлебозавод №1', 'Мука пшеничная в/с, вода, дрожжи, соль, сахар', 265.00, 8.50, 3.20, 49.00),
(3, 1, 'Яйца куриные С0', 'yaytsa-kurinye-s0', 'Яйца куриные отборные, категория С0, 10 штук', 129.90, 'https://images.unsplash.com/photo-1582722872445-44dc5f7e3c8f?w=400', 1, 'Птицефабрика', 'Яйца куриные', 157.00, 12.70, 11.50, 0.70),
(4, 1, 'Сыр Российский', 'syr-rossiyskiy', 'Сыр полутвердый Российский 50% жирности, 200г', 189.50, 'https://images.unsplash.com/photo-1486297678162-eb2a19b0a32d?w=400', 0, 'Сырная компания', 'Молоко, соль, сычужный фермент, хлорид кальция', 360.00, 24.00, 29.00, 0.00),
(5, 2, 'Средство для мытья посуды', 'sredstvo-dlya-mytya-posudy', 'Гель для мытья посуды с лимоном, эффективно удаляет жир, 500мл', 79.90, 'https://images.unsplash.com/photo-1584820927498-cfe5211fd8bf?w=400', 0, 'Frosch', 'ПАВ 5-15%, лимонная кислота, ароматизатор', NULL, NULL, NULL, NULL),
(6, 2, 'Стиральный порошок', 'stiralnyy-poroshok', 'Универсальный стиральный порошок автомат, 3кг', 349.00, 'https://images.unsplash.com/photo-1610557892470-55d9e80c0bce?w=400', 1, 'Persil', 'ПАВ, энзимы, оптические отбеливатели, parfum', NULL, NULL, NULL, NULL),
(7, 3, 'Вода минеральная', 'voda-mineralnaya', 'Вода минеральная негазированная, природная, 1.5л', 59.90, 'https://images.unsplash.com/photo-1548839140-29a749e1cf4d?w=400', 0, 'BonAqua', 'Вода артезианская', 0.00, 0.00, 0.00, 0.00),
(8, 3, 'Сок апельсиновый', 'sok-apelsinovyy', 'Сок апельсиновый прямого отжима, 100% натуральный, 1л', 149.90, 'https://images.unsplash.com/photo-1600271886742-f049cd451bba?w=400', 1, 'Rich', 'Сок апельсиновый восстановленный', 45.00, 0.70, 0.20, 10.40);

-- Администратор (логин: admin, пароль: admin123)
-- Хеш пароля сгенерирован через password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO `admins` (`id`, `username`, `password_hash`) VALUES
(1, 'admin', '$2y$10$jY0/QX8N2Ioyg7ZLGTELj.uSeWIitOqmFqmmsv.6Kk0fVSqDYwusW');

COMMIT;
