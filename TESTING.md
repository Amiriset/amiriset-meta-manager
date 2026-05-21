# WordPress Unit Tests Setup

Окружение для запуска Unit Tests в WordPress проекте настроено.

## Быстрый старт

### 1. Установка зависимостей
```bash
composer install
```

### 2. Установка WordPress тестового окружения
```bash
bash bin/install-wp-tests.sh wordpress_test root root localhost latest
```

Параметры скрипта:
- `wordpress_test` — имя тестовой БД
- `root` — пользователь БД
- `root` — пароль БД
- `localhost` — хост БД
- `latest` — версия WordPress (или конкретная версия, например `6.1`)

### 3. Запуск тестов
```bash
composer test
```

### 4. Запуск тестов с отчётом о покрытии
```bash
composer test-coverage
```

### 5. Проверка кода по стандартам WordPress
```bash
composer lint
```

### 6. Автоматическое исправление кода
```bash
composer lint-fix
```

## Структура

- **composer.json** — зависимости (PHPUnit, WordPress CLI, стандарты кодирования)
- **phpunit.xml.dist** — конфигурация PHPUnit
- **.phpcs.xml** — стандарты кодирования WordPress
- **tests/bootstrap.php** — загрузчик тестового окружения
- **tests/Helpers/class-test-case.php** — базовый класс для всех тестов
- **bin/install-wp-tests.sh** — установщик окружения

## GitHub Actions

Тесты автоматически запускаются при push в ветки `main` и `develop`:
- PHP 7.4, 8.0, 8.1, 8.2
- WordPress 6.1, 6.2, latest

## Требования

- PHP >= 7.4
- MySQL/MariaDB
- Composer
- bash

## Написание тестов

Все тесты должны наследоваться от `Amiriset_Meta_Manager_Test_Case`:

```php
<?php

class Test_My_Feature extends Amiriset_Meta_Manager_Test_Case {
    public function test_something() {
        $this->assertTrue( true );
    }
}
```

Тесты должны находиться в директории `tests/` и заканчиваться на `Test.php`.
