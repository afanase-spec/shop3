-- Обновление таблицы products - добавление новых полей
-- Выполнить в phpMyAdmin на вкладке SQL

ALTER TABLE `products` 
ADD COLUMN `manufacturer` varchar(200) DEFAULT NULL AFTER `is_popular`,
ADD COLUMN `composition` text DEFAULT NULL AFTER `manufacturer`,
ADD COLUMN `calories` decimal(6,2) DEFAULT NULL AFTER `composition`,
ADD COLUMN `proteins` decimal(6,2) DEFAULT NULL AFTER `calories`,
ADD COLUMN `fats` decimal(6,2) DEFAULT NULL AFTER `proteins`,
ADD COLUMN `carbohydrates` decimal(6,2) DEFAULT NULL AFTER `fats`;
