DOCKER:= docker
DOCKER_COMPOSE:= docker compose
NPX:= npx
COMPOSE_PROJECT_NAME ?= episciences-api
CNTR_NAME_HTTPD := $(COMPOSE_PROJECT_NAME)-httpd
CNTR_NAME_PHP := php-fpm-api
CNTR_APP_DIR := /var/www/htdocs
CNTR_APP_USER := www-data

MYSQL_CONNECT_EPISCIENCES:= mysql -u root -proot -h 127.0.0.1 -P 33060 episciences
MYSQL_CONNECT_AUTH:= mysql -u root -proot -h 127.0.0.1 -P 33062 cas_users

.PHONY: build up down clean help

help: ## Display this help
	@echo "Available targets:"
	@grep -E '^[a-zA-Z_-]+:.*##' Makefile | awk 'BEGIN {FS = ":.*?## "}; {printf "%-30s %s\n", $$1, $$2}'

build: ## Build the docker containers
	$(DOCKER_COMPOSE) build

up: ## Start all the docker containers
	$(DOCKER_COMPOSE) up -d
	@echo "====================================================================="
	@echo "Make sure you have [127.0.0.1 localhost api-local.episciences.org] in /etc/hosts"
	@echo "Episciences Api : http://api-local.episciences.org/"
	@echo "PhpMyAdmin  : http://localhost:8001/"
	@echo "====================================================================="
	@echo "SQL Place Custom SQL dump files in ~/tmp/"
	@echo "SQL: Import '~/tmp/episciences.sql' with 'make load-db-episciences'"

down: ## Stop the docker containers and remove orphans
	$(DOCKER_COMPOSE) down --remove-orphans

clean: down ## Clean up unused docker resources
	#docker stop $(docker ps -a -q)
	docker system prune -f

load-db-manager: ## Load an SQL dump from ./tmp/episciences.sql
	$(MYSQL_CONNECT_EPISCIENCES) < ~/tmp/episciences.sql

load-db-auth: ## Load an SQL dump from ./tmp/cas_users.sql
	$(MYSQL_CONNECT_AUTH) < ~/tmp/cas_users.sql

composer-install: ## Install composer dependencies
	$(DOCKER_COMPOSE) exec -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) composer install --no-interaction --prefer-dist --optimize-autoloader

composer-update: ## Update composer dependencies
	$(DOCKER_COMPOSE) exec -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) composer update --no-interaction --prefer-dist --optimize-autoloader

yarn-encore-production: ## yarn encore production
	$(DOCKER_COMPOSE) exec -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) yarn install; yarn encore production

restart-httpd: ## Restart Apache httpd
	$(DOCKER_COMPOSE) restart $(CNTR_NAME_HTTPD)

restart-php: ## Restart PHP-FPM Container
	$(DOCKER_COMPOSE) restart $(CNTR_NAME_PHP)

can-i-use-update: ## To be launched when Browserslist: caniuse-lite is outdated.
	$(NPX) update-browserslist-db@latest

enter-container-php: ## Open shell on PHP container
	$(DOCKER) exec -it $(CNTR_NAME_PHP) sh -c "cd /var/www/htdocs && /bin/bash"

enter-container-httpd: ## Open shell on HTTPD container
	$(DOCKER) exec -it $(CNTR_NAME_HTTPD) sh -c "cd /var/www/htdocs && /bin/bash"