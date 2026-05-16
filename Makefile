.PHONY: up down logs build migrate test test-backend test-frontend install

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

## Backend unit tests (PHPUnit)
test-backend:
	cd backend && vendor/bin/phpunit

## Frontend unit tests (Vitest)
test-frontend:
	cd frontend && pnpm run test
