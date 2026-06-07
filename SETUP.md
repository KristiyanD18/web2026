# Система за Входиране на Документи — Setup Guide

## XAMPP (локално)

1. Копирайте папката `docreg-5MI0600358-2MI0600298` в `C:\xampp\htdocs\w26\`
2. Редактирайте `.env`:
   ```
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=docreg
   DB_USER=root
   DB_PASS=
   APP_BASE_URL=http://localhost
   APP_BASE_PATH=/w26/docreg-5MI0600358-2MI0600298
   ```
3. В phpMyAdmin създайте база `docreg` и изпълнете `sql/schema.sql`
4. Отворете `http://localhost/w26/docreg-5MI0600358-2MI0600298/`

## Docker

1. Копирайте `.env.example` → `.env` и задайте паролите
2. `docker-compose up -d`
3. Отворете `http://localhost:8080`
4. phpMyAdmin: `http://localhost:8081`

## Акаунти по подразбиране

| Username | Парола     | Роля          |
|----------|------------|---------------|
| admin    | password   | Администратор |

**ВАЖНО:** Сменете паролата веднага след първи вход!

## PHP разширения (XAMPP)

Уверете се, че са включени в `php.ini`:
- `extension=pdo_mysql`
- `extension=gd`
- `extension=openssl`
- `extension=fileinfo`

## Структура на URL

| Страница             | URL |
|----------------------|-----|
| Входиране (публично) | `/submit.php` |
| Проследяване         | `/track.php` |
| Вход                 | `/public/login.php` |
| Администрация        | `/public/admin/index.php` |
| Отговорник           | `/public/officer/index.php` |
