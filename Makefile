CASE = tests/

up:
	docker compose up -d
build:
	docker compose build --no-cache --force-rm
init:
	@make build
	@make up
	docker compose up -d --build
	docker compose exec app composer install
	docker compose exec app cp .env.example .env
	docker compose exec app cp .env.testing.example .env.testing
	docker compose exec app php artisan key:generate
	docker compose exec app php artisan storage:link
	docker compose exec app php artisan optimize
	docker compose exec app php artisan optimize:clear
	docker compose exec app chmod -R 777 storage bootstrap/cache public/bootstrap-icons public/images public/tmp
	@make migrate
stop:
	docker compose stop
down:
	docker compose down --remove-orphans
restart:
	@make down
	@make up
destroy:
	docker compose down --rmi all --volumes --remove-orphans
	rm -Rf .docker/db/data/*
ps:
	docker compose ps
logs:
	docker compose logs
migrate:
	docker compose exec app php artisan migrate:refresh --seed
test:
	docker compose exec app vendor/bin/phpunit -c tests/ --colors=always ${CASE}
tinker:
	docker compose exec app php artisan tinker
optimize:
	docker compose exec app php artisan optimize
optimize-clear:
	docker compose exec app php artisan optimize:clear
cache:
	docker compose exec app composer dump-autoload -o
	@make optimize
	docker compose exec app php artisan event:cache
	docker compose exec app php artisan view:cache
cache-clear:
	docker compose exec app composer clear-cache
	@make optimize-clear
	docker compose exec app php artisan event:clear
npm:
	@make npm-install
npm-install:
	docker compose exec web npm install
npm-dev:
	docker compose exec web npm run dev
npm-watch:
	docker compose exec web npm run watch
npm-watch-poll:
	docker compose exec web npm run watch-poll
npm-hot:
	docker compose exec web npm run hot
npm-prod:
	docker compose exec web npm run prod
yarn:
	docker compose exec web yarn
yarn-install:
	@make yarn
yarn-dev:
	docker compose exec web yarn dev
yarn-watch:
	docker compose exec web yarn watch
yarn-watch-poll:
	docker compose exec web yarn watch-poll
yarn-hot:
	docker compose exec web yarn hot
app:
	docker compose exec app bash
db:
	docker compose exec db bash
ide-helper:
	docker compose exec app php artisan clear-compiled
	docker compose exec app php artisan ide-helper:generate
	docker compose exec app php artisan ide-helper:meta
	docker compose exec app php artisan ide-helper:models --nowrite