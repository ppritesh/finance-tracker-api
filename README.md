# Finance Tracker Backend

Laravel API for the [Finance Tracker](../finance-tracker) Flutter app.

## Features

- **Auth** — register, login, logout (Laravel Sanctum bearer tokens)
- **Persons** — contact list for credit tracking
- **Transactions** — unified ledger for credit given and expenses (INR only)
- **Summary** — dashboard totals

## Setup

```bash
cd D:\finance-tracker-backend
composer install
cp .env.example .env   # if .env does not exist
php artisan key:generate
php artisan migrate
php artisan serve
```

API base URL: `http://127.0.0.1:8000/api`

## API

### Register

`POST /api/auth/register`

```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

### Transactions

`GET /api/transactions` — filters: `type`, `status`, `person_id`, `from`, `to`

`POST /api/transactions` — create credit or expense

`POST /api/transactions/{id}/mark-received` — mark credit as received

### Summary

`GET /api/summary`
