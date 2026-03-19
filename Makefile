SHELL := /bin/bash

up:
	docker compose up -d --build

down:
	docker compose down -v

stop:
	docker compose stop

logs:
	docker compose logs -f app

ps:
	docker compose ps

sh:
	docker compose exec app bash || docker compose exec app sh

console:
	docker compose exec app php bin/console $(cmd)

cc:
	docker compose exec app php bin/console cache:clear

install:
	docker compose run --rm app composer install --no-interaction --prefer-dist
