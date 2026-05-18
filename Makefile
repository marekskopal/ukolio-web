.PHONY: up down logs build migrate test test-backend test-backend-coverage test-frontend lint lint-backend lint-fix install

## Start stack
up:
	docker compose up -d --build

## Stop stack
down:
	docker compose down

## Tail logs
logs:
	docker compose logs -f

## Rebuild without cache
build:
	docker compose build --no-cache

## Install backend + frontend dependencies (host)
install:
	cd backend && composer install
	cd frontend && pnpm install

## Run database migrations
migrate:
	docker compose exec backend php bin/console migration:run

## Run all tests
test: test-backend test-frontend

## Backend unit tests (PHPUnit). Runs inside the backend container so the test
## suite can reach MariaDB. The harness auto-creates the `ukolio_test` database
## using the backend's MYSQL_USER credentials (must have CREATE privilege).
test-backend:
	docker compose exec -T -e TEST_MYSQL_DATABASE=ukolio_test backend vendor/bin/phpunit

## Backend unit tests with coverage report at backend/.phpunit.cache/coverage-html
## Uses pcov (compiled into the backend image). Coverage focuses on src/Controller,
## src/Mcp, and src/Service per the UPB-49 acceptance criteria.
test-backend-coverage:
	docker compose exec -T -e TEST_MYSQL_DATABASE=ukolio_test backend vendor/bin/phpunit --coverage-text --coverage-html .phpunit.cache/coverage-html

## Frontend unit tests (Vitest)
test-frontend:
	cd frontend && pnpm run test

## Backend static analysis + code style
lint: lint-backend

lint-backend:
	cd backend && vendor/bin/phpstan analyse --no-progress
	cd backend && vendor/bin/phpcs

## Auto-fix backend code style
lint-fix:
	cd backend && vendor/bin/phpcbf
