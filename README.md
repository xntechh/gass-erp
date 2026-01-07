# GASS ERP

GASS ERP is a Laravel + Filament application for managing inventory operations across plants and warehouses. It provides a browser-based admin panel to track items, stock levels, and warehouse transactions with audit logging.

## Key Features

- Item, unit, and category management
- Multi-warehouse inventory tracking and valuation widgets
- Stock opname (cycle counts) with adjustment handling
- Inbound/outbound warehouse transactions with details
- Activity logging and PDF/Excel exports

## Prerequisites

- PHP 8.2+
- Composer
- Node.js + npm
- SQLite (default) or MySQL/PostgreSQL

## Environment Variables

Copy `.env.example` to `.env` and set at least:

- `APP_KEY` (generated via artisan)
- `APP_URL`
- `DB_CONNECTION`
- `DB_DATABASE` (for SQLite, the path to the database file)

Optional for other database engines: `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# SQLite example
mkdir -p database
: > database/database.sqlite

php artisan migrate
npm install
```

## Running the App

```bash
# One command (server, queue, logs, Vite)
composer run dev
```

Or run pieces separately:

```bash
php artisan serve
php artisan queue:listen --tries=1
npm run dev
```

## Running Tests

```bash
php artisan test
```
