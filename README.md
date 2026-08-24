# Brighter Day SMIS

School management information system. Built from [Brighter_Day_SMIS_Build_PRD.md](./Brighter_Day_SMIS_Build_PRD.md) — see that file for the full spec, schema, API surface, and build phases.

**Status:** Phase 0 (Foundation) complete — auth, RBAC middleware, and queued email are working end to end.

## Stack

- Backend: Laravel 13 (PHP 8.3+), REST API, PostgreSQL, Sanctum token auth
- Frontend: React (Vite), react-router-dom, axios

## Running locally

### Prerequisites
- PHP 8.3+ and Composer
- Node 18+ and npm
- PostgreSQL 14+ running locally

### Backend

```
cd backend
composer install
cp .env.example .env   # then set DB_* to match your local Postgres
php artisan key:generate
php artisan migrate
php artisan serve
```

Create the databases first if they don't exist:

```
psql -U postgres -c "CREATE DATABASE brighter_day_smis;"
psql -U postgres -c "CREATE DATABASE brighter_day_smis_testing;"
```

Run tests:

```
php artisan test
```

### Frontend

```
cd frontend
npm install
npm run dev
```

Set `VITE_API_URL` in `frontend/.env` to point at the backend (defaults to `http://127.0.0.1:8000/api/v1`).

## Notes

- Queued mail uses Laravel's `log`/`array` mail driver in dev — no real SMTP required to see the async email flow working (check `storage/logs/laravel.log` for the `log` driver, or the `email_log` table for delivery status).
- File upload limits (Section 8, item 4 of the PRD): photos JPG/PNG max 2MB, documents (CV/transcript) PDF max 5MB — see `backend/config/uploads.php`.
