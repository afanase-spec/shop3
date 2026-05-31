# Обновление базы данных - Инструкция

## Шаг 1: Выполните SQL скрипт в phpMyAdmin

### Вариант A: Использовать готовый скрипт
1. Откройте phpMyAdmin (http://localhost/phpmyadmin)
2. Выберите базу данных `shop2`
3. Перейдите на вкладку "SQL"
4. Скопируйте и выполните содержимое файла `UPDATE_PRODUCTS_TABLE.sql`

### Вариант B: Вручную выполнить ALTER TABLE
```sql
ALTER TABLE `products` 
ADD COLUMN `manufacturer` varchar(200) DEFAULT NULL AFTER `is_popular`,
ADD COLUMN `composition` text DEFAULT NULL AFTER `manufacturer`,
ADD COLUMN `calories` decimal(6,2) DEFAULT NULL AFTER `composition`,
ADD COLUMN `proteins` decimal(6,2) DEFAULT NULL AFTER `calories`,
ADD COLUMN `fats` decimal(6,2) DEFAULT NULL AFTER `proteins`,
ADD COLUMN `carbohydrates` decimal(6,2) DEFAULT NULL AFTER `fats`;
```

## Шаг 2: Проверьте результат

После выполнения запроса таблица `products` должна содержать следующие новые поля:
- `manufacturer` - производитель товара
- `composition` - состав товара
- `calories` - калории (на 100г)
- `proteins` - белки (на 100г)
- `fats` - жиры (на 100г)
- `carbohydrates` - углеводы (на 100г)

## Шаг 3: Обновите тестовые данные (опционально)

Если вы хотите обновить существующие товары с данными о пищевой ценности:

1. Удалите старые тестовые данные:
```sql
DELETE FROM products;
```

2. Вставьте обновленные данные из файла `install.sql` (строки 127-143 содержат примеры с БЖУ)

## Готово!

После выполнения этих шагов:
✅ Админ-панель сможет сохранять производителя, состав и БЖУ
✅ Страница товара будет отображать эту информацию
✅ Таблица БЖУ появится только для продуктов питания (где заполнены калории)
✅ Кнопка "Добавить в корзину" стилизована под овальный дизайн с ценой
