# RackTimeGym

A capacity and utilization app for independent gyms. See [CONTEXT.md](CONTEXT.md) for the domain model.

## Stack

- `backend/` — Laravel API
- `frontend/` — React + shadcn/ui, built with Vite
- MySQL — the backend's database

## Running locally

Requires Docker and Docker Compose.

```bash
docker compose up
```

This starts all three services:

- Backend at [http://localhost:8000](http://localhost:8000)
- Frontend at [http://localhost:5173](http://localhost:5173)
- MySQL, reachable from the backend only (not exposed on the host)

On first run the backend waits for MySQL to accept connections, then runs migrations automatically.

Open [http://localhost:5173](http://localhost:5173) — it should show a "Connected" badge, confirming the frontend reached the backend and the backend reached the database.

Stop the stack with `docker compose down` (add `-v` to also drop the MySQL data volume).

## Backend health check

`GET /api/health` on the backend returns:

```json
{ "status": "ok", "database": "connected" }
```

with an HTTP 503 and `"database": "disconnected"` if the database is unreachable.

## Developing without Docker

**Backend** (requires PHP 8.3+, Composer, and a local MySQL or SQLite):

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

**Frontend** (requires Node 20+):

```bash
cd frontend
npm install
npm run dev
```

Set `VITE_API_URL` (e.g. in `frontend/.env.local`) to point the frontend at your backend, e.g. `http://localhost:8000/api`.
