.DEFAULT_GOAL := help

APP_CONTAINER=php
DB_CONTAINER=postgres
DB_NAME=finance
DB_USER=finance

## ========================
## 🆘 Pomoc
## ========================
help:
	@echo ""
	@echo "Dostępne komendy:"
	@echo ""
	@echo "  make build           - zbuduj i uruchom kontenery (up -d --build)"
	@echo "  make up              - uruchom kontenery (up -d)"
	@echo "  make down            - zatrzymaj kontenery"
	@echo "  make restart         - restart kontenerów (down && build)"
	@echo ""
	@echo "  make logs            - logi nginx + php"
	@echo "  make shell           - shell w kontenerze PHP"
	@echo ""
	@echo "  make install         - composer install"
	@echo "  make cache-clear     - wyczyść cache Symfony"
	@echo ""
	@echo "  make diff-dry        - SQL niezbędny do zsynchronizowania bazy danych z encjami (dry run)"
	@echo "  make diff            - wygeneruj migrację Doctrine"
	@echo "  make migrate         - uruchom migracje"
	@echo "  make db-reset        - drop + create + migrate"
	@echo ""
	@echo "  make fixtures        - załaduj fixtures"
	@echo ""
	@echo "  make db-dump         - dump bazy do pliku"
	@echo "  make db-restore      - restore bazy z dumpa"
	@echo ""
	@echo "  make test            - komplet testów (lint:twig, phpunit, phpstan, php-cs-fixer) na czystej bazie"
	@echo ""
	@echo "  phpstan              - phpstan"
	@echo ""
	@echo "  php-cs-fixer         - php-cs-fixer"
	@echo ""

## ========================
## 🐳 Docker
## ========================
build:
	docker compose up -d --build

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose down
	docker compose up -d --build

logs:
	docker compose logs -f php nginx

## ========================
## 🐚 Shell
## ========================
shell:
	docker compose exec $(APP_CONTAINER) bash

## ========================
## 📦 Symfony / Composer
## ========================
install:
	docker compose exec $(APP_CONTAINER) composer install
	docker compose exec -e JWT_PASSPHRASE= $(APP_CONTAINER) php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction

cache-clear:
	docker compose exec $(APP_CONTAINER) php bin/console cache:clear

## ========================
## 🗄️ Doctrine
## ========================
diff-dry:
	docker compose exec $(APP_CONTAINER) php bin/console doctrine:schema:update --dump-sql

diff:
	docker compose exec $(APP_CONTAINER) php bin/console doctrine:migrations:diff

migrate:
	docker compose exec $(APP_CONTAINER) php bin/console doctrine:migrations:migrate --no-interaction

db-reset:
	docker compose exec $(APP_CONTAINER) php bin/console doctrine:database:drop --force
	docker compose exec $(APP_CONTAINER) php bin/console doctrine:database:create
	docker compose exec $(APP_CONTAINER) php bin/console doctrine:migrations:migrate --no-interaction

fixtures:
	docker compose exec $(APP_CONTAINER) php bin/console doctrine:fixtures:load --no-interaction

## ========================
## 💾 Backup bazy
## ========================
db-dump:
	docker compose exec $(DB_CONTAINER) \
		pg_dump -U $(DB_USER) $(DB_NAME) > docker/postgres/backup.sql

db-restore:
	docker compose exec -T $(DB_CONTAINER) \
		psql -U $(DB_USER) $(DB_NAME) < docker/postgres/backup.sql

## ========================
## testy
## ========================

TEST_PATH ?=

test:
	docker exec finance-php-dev php bin/console cache:clear -n --env=test
	docker exec finance-php-dev php bin/console doctrine:database:drop --force --env=test || true
	docker exec finance-php-dev php bin/console doctrine:database:create --env=test
	docker exec finance-php-dev php bin/console doctrine:migrations:migrate -n --env=test
	docker exec finance-php-dev php bin/console doctrine:fixtures:load -n --env=test
	docker exec finance-php-dev php bin/console cache:clear -n --env=test
	docker exec finance-php-dev php bin/console lint:twig templates --env=test
	docker exec -t finance-php-dev vendor/bin/phpunit --colors=always tests/$(TEST_PATH)
	docker exec finance-php-dev vendor/bin/phpstan analyse -l 9 --memory-limit 1G src tests
	docker exec finance-php-dev vendor/bin/php-cs-fixer fix src

phpstan:
	docker exec finance-php-dev vendor/bin/phpstan analyse -l 9 --memory-limit 1G src tests

php-cs-fixer:
	docker exec finance-php-dev vendor/bin/php-cs-fixer fix src