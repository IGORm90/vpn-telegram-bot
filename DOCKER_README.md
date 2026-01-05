# Docker Setup для VPN Bot

## Структура

Проект включает три основных сервиса:
- **app** - PHP 8.2-FPM приложение
- **webserver** - Nginx веб-сервер
- **db** - MySQL 8.0 база данных

## Быстрый старт

### 1. Создайте файл .env

Скопируйте .env.example и настройте параметры:

```bash
cp .env.example .env
```

Минимальные настройки для .env:
```env
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=vpn_bot
DB_USERNAME=vpn_user
DB_PASSWORD=secret
```

### 2. Запустите контейнеры

```bash
docker-compose up -d
```

### 3. Установите зависимости (если нужно)

```bash
docker-compose exec app composer install
```

### 4. Выполните миграции

```bash
docker-compose exec app php artisan migrate
```

## Доступ к сервисам

- **Приложение**: http://localhost:8000
- **MySQL**: localhost:3306

## Полезные команды

### Просмотр логов
```bash
docker-compose logs -f
docker-compose logs -f app
docker-compose logs -f webserver
docker-compose logs -f db
```

### Остановка контейнеров
```bash
docker-compose down
```

### Остановка с удалением volumes
```bash
docker-compose down -v
```

### Перезапуск сервисов
```bash
docker-compose restart
```

### Выполнение команд в контейнере
```bash
# Artisan команды
docker-compose exec app php artisan [command]

# Composer
docker-compose exec app composer [command]

# Bash в контейнере приложения
docker-compose exec app bash

# MySQL консоль
docker-compose exec db mysql -u vpn_user -psecret vpn_bot
```

### Пересборка контейнеров
```bash
docker-compose build --no-cache
docker-compose up -d --force-recreate
```

## Структура файлов Docker

```
vpn-bot/
├── Dockerfile              # Образ PHP приложения
├── docker-compose.yml      # Оркестрация сервисов
├── .dockerignore           # Исключения для Docker
└── docker/
    └── nginx/
        └── conf.d/
            └── default.conf # Конфигурация Nginx
```

## Переменные окружения

В `docker-compose.yml` можно переопределить следующие переменные:

| Переменная | По умолчанию | Описание |
|-----------|--------------|----------|
| DB_DATABASE | vpn_bot | Имя базы данных |
| DB_USERNAME | vpn_user | Пользователь БД |
| DB_PASSWORD | secret | Пароль БД |

## Troubleshooting

### Проблемы с правами доступа к storage/
```bash
docker-compose exec app chmod -R 775 storage/
docker-compose exec app chown -R www-data:www-data storage/
```

### Очистка кэша
```bash
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear
```

### Проверка статуса контейнеров
```bash
docker-compose ps
```

### Просмотр использования ресурсов
```bash
docker stats
```

