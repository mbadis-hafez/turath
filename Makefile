.PHONY: install up down dev migrate test lint lint-backend lint-frontend

# --- Setup -------------------------------------------------------------------

install: install-backend install-frontend
	@cp -n backend/.env.example backend/.env 2>/dev/null || true
	@echo ">> Don't forget: edit backend/.env if your MySQL credentials differ, then run 'make migrate'"

install-backend:
	cd backend && composer install
	cd backend && php artisan key:generate --ansi

install-frontend:
	cd frontend && npm ci

# --- Dev environment (Herd-managed MySQL + Redis; no Docker) -------------------

up:
	@lsof -nP -iTCP:3306 -sTCP:LISTEN >/dev/null 2>&1 || (echo "!! MySQL is not running. Start it from the Herd app." && exit 1)
	@lsof -nP -iTCP:6379 -sTCP:LISTEN >/dev/null 2>&1 || (echo "!! Redis is not running. Start it from the Herd app." && exit 1)
	@echo "MySQL + Redis are up (managed by Herd). Nothing else to start."

down:
	@echo "MySQL/Redis are managed by Herd — stop them from the Herd app if needed."

migrate:
	cd backend && php artisan migrate:fresh --seed

# --- Running -------------------------------------------------------------------

dev:
	@(cd backend && php artisan serve --port=8000 &) && cd frontend && npm run dev

# --- Quality -------------------------------------------------------------------

test: test-backend test-frontend

test-backend:
	cd backend && php artisan test --parallel

test-frontend:
	cd frontend && npm run test:run

lint: lint-backend lint-frontend

lint-backend:
	cd backend && ./vendor/bin/pint --test
	cd backend && ./vendor/bin/phpstan analyse --no-progress
	cd backend && php artisan test --parallel

lint-frontend:
	cd frontend && npm run lint
	cd frontend && npm run typecheck
	cd frontend && npm run test:run
