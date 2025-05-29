## Швидкий старт

1. **Клонування репозиторію:**
```bash
git clone https://github.com/VasylHryn/catalog_test
cd catalog_test
```

2. **Встановлення залежностей:**
```bash
composer install
```

3. **Налаштування середовища:**
```bash
cp .env.example .env
```
Відредагуйте `.env` файл, вказавши ваші налаштування:
```env
DB_HOST=localhost
DB_NAME=catalog
DB_USER=root
DB_PASS=password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
```

4. **Створення бази даних:**
```sql
CREATE DATABASE catalog CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

5. **Запуск міграцій:**
```bash
php bin/console app:migrate
```

6. **Імпорт тестових даних:**
```bash
php bin/console app:import-xml
```

7. **Перевірка даних:**
```bash
php bin/console app:check-data
```

8. **Запуск локального серверу:**
```bash
php -S localhost:8000 -t public
```
Після цього додаток буде доступний за адресою: http://localhost:8000

## Структура проекту

```
catalog/
├── bin/
│   └── console                     # Консольний додаток
├── data/
│   └── catalog.xml                 # Тестові дані
├── public/                         # Публічна директорія
│   ├── css/
│   │   └── style.css              # Стилі
│   ├── js/
│   │   └── app.js                 # JavaScript логіка
│   ├── .htaccess                  # Apache конфігурація
│   ├── api.php                    # API endpoint
│   ├── index.html                 # Frontend
│   └── index.php                  # Точка входу
├── src/
│   ├── Cache/
│   │   └── RedisCache.php         # Робота з Redis кешем
│   ├── Command/                   # Консольні команди
│   │   ├── CheckDataCommand.php
│   │   ├── ImportXmlCommand.php
│   │   └── MigrateCommand.php
│   ├── Config/                    # Конфігурація
│   │   ├── Database.php
│   │   └── Redis.php
│   ├── Controller/                # API контролери
│   │   ├── ParameterController.php
│   │   └── ProductController.php
│   ├── Database/                  # Робота з базою даних
│   │   ├── Migrations/
│   │   │   ├── CreateParametersTable.php
│   │   │   ├── CreateParameterValuesTable.php
│   │   │   ├── CreateProductParametersTable.php
│   │   │   ├── CreateProductsTable.php
│   │   │   ├── Database.php
│   │   │   ├── Migration.php
│   │   │   └── MigrationRunner.php
│   │   ├── Database.php
│   │   ├── Migration.php
│   │   └── MigrationRunner.php
│   ├── Service/                   # Сервіси
│   │   ├── CacheUpdateService.php
│   │   └── RedisFilterService.php
│   └── Traits/                    # Трейти
│       └── UseRedis.php
└── tests/                         # Тести
    ├── Command/
    │   └── ImportXmlCommandTest.php
    └── Service/
        └── RedisFilterServiceTest.php
```

## API Endpoints

### Отримання продуктів
```http
GET http://localhost:8000/api/catalog/products
```

#### Базовий URL
```
http://localhost:8000
```

#### Endpoint
```
/api/catalog/products
```

#### Параметри
- `page` (int) - Номер сторінки
- `limit` (int) - Кількість продуктів на сторінці (за замовчуванням 10)
- `sort_by` (string) - Сортування (price_asc, price_desc)
- `filter[parameter]` (array) - Фільтри

#### Доступні фільтри

1. Англійське найменування
```http
GET http://localhost:8000/api/catalog/products?filter[angl-yske-naymenuvannya]=COMBI
```

2. Бренд
```http
GET http://localhost:8000/api/catalog/products?filter[brend]=Nike
```

3. Колір
```http
GET http://localhost:8000/api/catalog/products?filter[kol-r]=Чорний
```

4. Призначення
```http
GET http://localhost:8000/api/catalog/products?filter[priznachennya]=Плавання
```

5. Розмір постачальника
```http
GET http://localhost:8000/api/catalog/products?filter[rozm-r-postachalnika]=XL
```

6. Склад
```http
GET http://localhost:8000/api/catalog/products?filter[sklad]=Силікон
```

7. Стать
```http
GET http://localhost:8000/api/catalog/products?filter[stat]=Унісекс
```

#### Приклади запитів

1. Базовий запит (перша сторінка):
```http
GET http://localhost:8000/api/catalog/products
```

2. Пагінація:
```http
GET http://localhost:8000/api/catalog/products?page=2&limit=20
```

3. Сортування за ціною (від дешевих до дорогих):
```http
GET http://localhost:8000/api/catalog/products?sort_by=price_asc
```

4. Фільтрація за кольором:
```http
GET http://localhost:8000/api/catalog/products?filter[kol-r]=Чорний
```

5. Комбінована фільтрація (колір та бренд):
```http
GET http://localhost:8000/api/catalog/products?filter[kol-r]=Чорний&filter[brend]=Nike
```

6. Повний приклад з усіма типами параметрів:
```http
GET http://localhost:8000/api/catalog/products?page=2&limit=20&sort_by=price_desc&filter[kol-r]=Чорний&filter[stat]=Унісекс
```

#### Приклад відповіді

```json
{
    "data": [
        {
            "id": 515,
            "name": "Напульсник UA Performance Wristbands-BLK чорний Чол UNI",
            "price": "490.00",
            "status": 1,
            "description": "Мужские напульсники Under Armour\n57% хлопок / 38% поліестер / 5% эластан",
            "created_at": "2025-05-28 21:28:29",
            "updated_at": "2025-05-28 21:28:29"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 10,
        "total": 10228,
        "last_page": 1023
    }
}
```

#### Коди відповідей

- `200 OK` - Успішний запит
- `400 Bad Request` - Некоректні параметри запиту
- `404 Not Found` - Сторінка не знайдена
- `500 Internal Server Error` - Помилка сервера

## Команди

- `php bin/console app:migrate` - Запуск міграцій
- `php bin/console app:migrate --rollback` - Відкат міграцій
- `php bin/console app:import-xml` - Імпорт даних
- `php bin/console app:check-data` - Перевірка даних

## Тестування

Для запуску тестів необхідно налаштувати доступи до бази даних та Redis у файлі `phpunit.xml.dist`

```bash
composer test
```
