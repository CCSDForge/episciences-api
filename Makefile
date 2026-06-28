# Episciences API - Development Makefile
# =====================================

# Colors for output
RED=\033[0;31m
GREEN=\033[0;32m
YELLOW=\033[0;33m
BLUE=\033[0;34m
BOLD=\033[1m
NC=\033[0m # No Color

# Docker Compose command detection
DOCKER_COMPOSE := $(shell if command -v docker-compose >/dev/null 2>&1; then echo "docker-compose"; elif docker compose version >/dev/null 2>&1; then echo "docker compose"; else echo ""; fi)

# Default target
.DEFAULT_GOAL := help

# Phony targets
.PHONY: help check-prereqs install test test-unit test-coverage cov test-file validate clean phpstan rector local-rector check docker-up docker-up-ci docker-down docker-down-ci docker-restart docker-logs docker-status docker-shell docker-mysql docker-test docker-test-coverage docker-test-unit docker-install docker-install-ci docker-composer docker-composer-update setup-help deploy deploy-branch deploy-tag

# Help target - displays all available commands
help:
	@echo "$(BOLD)Episciences API - Development Commands$(NC)"
	@echo "======================================"
	@echo ""
	@echo "$(BLUE)Setup Commands:$(NC)"
	@echo "  $(BOLD)check-prereqs$(NC)     Check if all prerequisites are installed"
	@echo "  $(BOLD)install$(NC)           Install PHP dependencies"
	@echo ""
	@echo "$(BLUE)Testing & Analysis Commands:$(NC)"
	@echo "  $(BOLD)test$(NC)              Run all PHPUnit tests"
	@echo "  $(BOLD)test-unit$(NC)         Run only unit tests"
	@echo "  $(BOLD)test-coverage$(NC)     Run tests with coverage report"
	@echo "  $(BOLD)test-file$(NC)         Run specific test file (usage: make test-file FILE=path/to/TestFile.php)"
	@echo "  $(BOLD)phpstan$(NC)           Run PHPStan in container (usage: make phpstan LEVEL=1 TARGET=src DRY_RUN=1)"
	@echo "  $(BOLD)rector$(NC)            Run Rector in container (usage: make rector TARGET=src DRY_RUN=1)"
	@echo "  $(BOLD)local-rector$(NC)      Run Rector on host (usage: make local-rector TARGET=src DRY_RUN=1)"
	@echo "  $(BOLD)check$(NC)             Run both PHPStan and Rector"
	@echo ""
	@echo "$(BLUE)Utility Commands:$(NC)"
	@echo "  $(BOLD)validate$(NC)          Check PHP syntax of test files"
	@echo "  $(BOLD)clean$(NC)             Clean cache and temporary files"
	@echo ""
	@echo "$(BLUE)Docker Development Commands:$(NC)"
	@echo "  $(BOLD)docker-up$(NC)         Start all containers (detached)"
	@echo "  $(BOLD)docker-up-ci$(NC)      Start containers with CI database (standalone)"
	@echo "  $(BOLD)docker-down$(NC)       Stop all containers"
	@echo "  $(BOLD)docker-down-ci$(NC)    Stop CI containers with volumes cleanup"
	@echo "  $(BOLD)docker-restart$(NC)    Restart all containers"
	@echo "  $(BOLD)docker-logs$(NC)       Follow container logs"
	@echo "  $(BOLD)docker-status$(NC)     Show container status"
	@echo "  $(BOLD)docker-shell$(NC)      Enter PHP container shell"
	@echo "  $(BOLD)docker-mysql$(NC)      Enter MySQL container shell"
	@echo "  $(BOLD)setup-help$(NC)        Show detailed setup instructions"
	@echo ""
	@echo "$(BLUE)Docker Testing Commands:$(NC)"
	@echo "  $(BOLD)docker-test$(NC)       Run tests in PHP container"
	@echo "  $(BOLD)docker-test-coverage$(NC) Run tests with coverage in PHP container"
	@echo "  $(BOLD)docker-test-unit$(NC)  Run unit tests only in PHP container"
	@echo "  $(BOLD)docker-install$(NC)    Install dependencies in container"
	@echo "  $(BOLD)docker-install-ci$(NC) Install dependencies (CI optimized)"
	@echo "  $(BOLD)docker-composer$(NC)   Run composer in container (usage: make docker-composer CMD='update')"
	@echo ""
	@echo "$(BLUE)Deployment Commands:$(NC)"
	@echo "  $(BOLD)deploy$(NC)             Deploy main branch to production"
	@echo "  $(BOLD)deploy-branch$(NC)      Deploy specific branch (usage: make deploy-branch BRANCH=develop)"
	@echo "  $(BOLD)deploy-tag$(NC)         Deploy specific tag (usage: make deploy-tag TAG=v1.0.0)"
	@echo ""
	@echo "$(YELLOW)Prerequisites (Local Development):$(NC)"
	@echo "  - PHP 8.3+ (php8.3 command)"
	@echo "  - Local composer binary (./composer)"
	@echo "  - OpenSSL (for SSL certificate generation)"
	@echo "  - Installed dependencies (vendor/)"
	@echo ""
	@echo "$(YELLOW)Prerequisites (Docker Development):$(NC)"
	@echo "  - Docker and Docker Compose installed"
	@echo "  - docker-compose.yml file configured"
	@echo "  - /etc/hosts configured (see 'make setup-help')"
	@echo ""
	@echo "Run '$(BOLD)make check-prereqs$(NC)' to verify local setup or '$(BOLD)make docker-status$(NC)' to check Docker."

# Check all prerequisites
check-prereqs:
	@echo "$(BOLD)Checking Prerequisites...$(NC)"
	@echo ""
	@# Check PHP 8.3
	@if ! command -v php8.3 >/dev/null 2>&1; then \
		echo "$(RED)✗ PHP 8.3 not found$(NC)"; \
		echo "  Install PHP 8.3:"; \
		echo "    Ubuntu/Debian: $(BOLD)sudo apt install php8.3 php8.3-cli php8.3-mbstring php8.3-xml php8.3-mysql$(NC)"; \
		echo "    CentOS/RHEL:   $(BOLD)sudo yum install php82 php82-cli php82-mbstring php82-xml php82-mysqlnd$(NC)"; \
		echo ""; \
		exit 1; \
	else \
		echo "$(GREEN)✓ PHP 8.3 found$(NC) ($$(php8.3 --version | head -n1))"; \
	fi
	@# Check local composer
	@if [ ! -f "./composer" ]; then \
		echo "$(RED)✗ Local composer not found$(NC)"; \
		echo "  Download composer:"; \
		echo "    $(BOLD)curl -sS https://getcomposer.org/installer | php8.3$(NC)"; \
		echo "    $(BOLD)mv composer.phar composer$(NC)"; \
		echo ""; \
		exit 1; \
	else \
		echo "$(GREEN)✓ Local composer found$(NC)"; \
	fi
	@# Check if composer is executable
	@if [ ! -x "./composer" ]; then \
		echo "$(YELLOW)⚠ Composer is not executable$(NC)"; \
		echo "  Make it executable: $(BOLD)chmod +x ./composer$(NC)"; \
		echo ""; \
		exit 1; \
	else \
		echo "$(GREEN)✓ Composer is executable$(NC)"; \
	fi
	@# Check vendor directory
	@if [ ! -d "vendor" ]; then \
		echo "$(YELLOW)⚠ Dependencies not installed$(NC)"; \
		echo "  Install dependencies: $(BOLD)make install$(NC)"; \
		echo ""; \
	else \
		echo "$(GREEN)✓ Dependencies installed$(NC)"; \
	fi
	@# Check PHPUnit
	@if [ ! -f "vendor/bin/phpunit" ]; then \
		echo "$(YELLOW)⚠ PHPUnit not found$(NC)"; \
		echo "  Install dependencies: $(BOLD)make install$(NC)"; \
		echo ""; \
	else \
		echo "$(GREEN)✓ PHPUnit available$(NC)"; \
	fi
	@echo ""
	@echo "$(GREEN)$(BOLD)✓ All prerequisites met!$(NC)"

# Install PHP dependencies
install: check-prereqs
	@echo "$(BOLD)Installing PHP dependencies...$(NC)"
	php8.3 ./composer install --no-progress --prefer-dist --optimize-autoloader
	@echo "$(GREEN)✓ Dependencies installed successfully$(NC)"


# Run all tests
test: check-prereqs
	@if [ ! -d "vendor" ]; then \
		echo "$(RED)Dependencies not installed. Run: make install$(NC)"; \
		exit 1; \
	fi
	@echo "$(BOLD)Running all PHPUnit tests...$(NC)"
	php8.3 vendor/bin/phpunit
	@echo "$(GREEN)✓ Tests completed$(NC)"

# Run only unit tests
test-unit: check-prereqs
	@if [ ! -d "vendor" ]; then \
		echo "$(RED)Dependencies not installed. Run: make install$(NC)"; \
		exit 1; \
	fi
	@echo "$(BOLD)Running unit tests...$(NC)"
	php8.3 vendor/bin/phpunit tests/Unit/
	@echo "$(GREEN)✓ Unit tests completed$(NC)"

# Run tests with coverage
test-coverage: check-prereqs
	@if [ ! -d "vendor" ]; then \
		echo "$(RED)Dependencies not installed. Run: make install$(NC)"; \
		exit 1; \
	fi
	@echo "$(BOLD)Running tests with coverage...$(NC)"
	./bin/coverage.sh
	@echo "$(GREEN)✓ Tests with coverage completed$(NC)"
	@echo "Coverage report available at: $(BOLD)coverage/index.html$(NC)"

# Shortcut for coverage
cov: test-coverage

# Run specific test file
test-file: check-prereqs
	@if [ -z "$(FILE)" ]; then \
		echo "$(RED)Usage: make test-file FILE=path/to/TestFile.php$(NC)"; \
		exit 1; \
	fi
	@if [ ! -f "$(FILE)" ]; then \
		echo "$(RED)Test file not found: $(FILE)$(NC)"; \
		exit 1; \
	fi
	@if [ ! -d "vendor" ]; then \
		echo "$(RED)Dependencies not installed. Run: make install$(NC)"; \
		exit 1; \
	fi
	@echo "$(BOLD)Running test file: $(FILE)$(NC)"
	php8.3 vendor/bin/phpunit $(FILE)
	@echo "$(GREEN)✓ Test file completed$(NC)"

# Validate PHP syntax of test files
validate:
	@echo "$(BOLD)Validating PHP syntax of test files...$(NC)"
	@find tests/ -name "*.php" -exec php8.3 -l {} \; | grep -v "No syntax errors detected" || true
	@echo "$(GREEN)✓ PHP syntax validation completed$(NC)"

# Run PHPStan in container
# Usage: make phpstan LEVEL=1 TARGET=src DRY_RUN=1
phpstan:
	@LEVEL=$${LEVEL:-1}; \
	TARGET=$${TARGET:-src}; \
	DRY_RUN_ARG=""; \
	if [ "$${DRY_RUN}" = "1" ]; then DRY_RUN_ARG="--dry-run"; fi; \
	echo "$(BOLD)Running PHPStan (Level $$LEVEL) on $$TARGET...$(NC)"; \
	$(DOCKER_COMPOSE) exec php vendor/bin/phpstan analyze $$TARGET --level=$$LEVEL $$DRY_RUN_ARG
	@echo "$(GREEN)✓ PHPStan completed$(NC)"

# Run Rector in container
# Usage: make rector TARGET=src DRY_RUN=1
rector:
	@TARGET=$${TARGET:-src}; \
	DRY_RUN_ARG=""; \
	if [ "$$DRY_RUN" = "1" ]; then DRY_RUN_ARG="--dry-run"; fi; \
	echo "$(BOLD)Running Rector on $$TARGET...$(NC)"; \
	$(DOCKER_COMPOSE) exec php vendor/bin/rector process $$TARGET $$DRY_RUN_ARG
	@echo "$(GREEN)✓ Rector completed$(NC)"

# Run Rector locally using host PHP
# Usage: make local-rector TARGET=src DRY_RUN=1
local-rector:
	@TARGET=$${TARGET:-src}; \
	DRY_RUN_ARG=""; \
	if [ "$$DRY_RUN" = "1" ]; then DRY_RUN_ARG="--dry-run"; fi; \
	echo "$(BOLD)Running Rector locally on $$TARGET...$(NC)"; \
	php8.3 vendor/bin/rector process $$TARGET $$DRY_RUN_ARG
	@echo "$(GREEN)✓ Local Rector completed$(NC)"

# Run both PHPStan and Rector
check: phpstan rector

# Clean cache and temporary files
clean:
	@echo "$(BOLD)Cleaning cache and temporary files...$(NC)"
	@rm -rf var/cache/*
	@rm -rf coverage/
	@echo "$(GREEN)✓ Cleanup completed$(NC)"

# Docker Development Commands
# ===========================

# Start all containers in detached mode
docker-up:
	@echo "$(BOLD)Starting Docker containers...$(NC)"
	@if [ -z "$(DOCKER_COMPOSE)" ]; then \
		echo "$(RED)✗ Docker Compose not found$(NC)"; \
		echo "  Install Docker Compose: https://docs.docker.com/compose/install/"; \
		exit 1; \
	fi
	@if [ ! -f "docker-compose.yml" ]; then \
		echo "$(RED)✗ docker-compose.yml not found$(NC)"; \
		echo "  Create docker-compose.yml first"; \
		exit 1; \
	fi
	$(DOCKER_COMPOSE) up -d
	@echo "$(GREEN)✓ Containers started$(NC)"
	@echo ""
	@echo "$(BOLD)🌐 Application URLs:$(NC)"
	@echo "  $(BLUE)HTTPS:$(NC) https://api-dev.episciences.org/ (via Traefik)"
	@echo "  $(BLUE)HTTP direct:$(NC)  http://api-dev.episciences.org:8080"
	@echo ""
	@echo "$(YELLOW)⚠️  Setup Required:$(NC)"
	@echo "  Add this line to your $(BOLD)/etc/hosts$(NC) file:"
	@echo "  $(BOLD)127.0.0.1    api-dev.episciences.org$(NC)"
	@echo ""
	@echo "  Run '$(BOLD)make setup-help$(NC)' for detailed setup instructions."

# Start containers with CI database (standalone)
docker-up-ci:
	@echo "$(BOLD)Starting Docker containers (CI mode with standalone database)...$(NC)"
	@if [ -z "$(DOCKER_COMPOSE)" ]; then \
		echo "$(RED)✗ Docker Compose not found$(NC)"; \
		echo "  Install Docker Compose: https://docs.docker.com/compose/install/"; \
		exit 1; \
	fi
	@if [ ! -f "docker-compose.yml" ] || [ ! -f "docker-compose.ci.yml" ]; then \
		echo "$(RED)✗ docker-compose.yml or docker-compose.ci.yml not found$(NC)"; \
		exit 1; \
	fi
	$(DOCKER_COMPOSE) -f docker-compose.yml -f docker-compose.ci.yml up -d
	@echo "$(GREEN)✓ CI containers started with standalone database$(NC)"
	@echo ""
	@echo "$(BOLD)🌐 Application URLs:$(NC)"
	@echo "  $(BLUE)HTTPS:$(NC) https://api-dev.episciences.org/ (via Traefik)"
	@echo "  $(BLUE)HTTP direct:$(NC)  http://api-dev.episciences.org:8080"

# Stop all containers
docker-down:
	@echo "$(BOLD)Stopping Docker containers...$(NC)"
	$(DOCKER_COMPOSE) down
	@echo "$(GREEN)✓ Containers stopped$(NC)"

# Stop CI containers with volume cleanup
docker-down-ci:
	@echo "$(BOLD)Stopping Docker containers (CI mode)...$(NC)"
	$(DOCKER_COMPOSE) -f docker-compose.yml -f docker-compose.ci.yml down -v
	@echo "$(GREEN)✓ CI containers stopped and volumes cleaned$(NC)"

# Restart all containers
docker-restart:
	@echo "$(BOLD)Restarting Docker containers...$(NC)"
	$(DOCKER_COMPOSE) restart
	@echo "$(GREEN)✓ Containers restarted$(NC)"
	@echo ""
	@echo "$(BOLD)🌐 Application URLs:$(NC)"
	@echo "  $(BLUE)HTTPS:$(NC) https://api-dev.episciences.org/ (via Traefik)"
	@echo "  $(BLUE)HTTP direct:$(NC)  http://api-dev.episciences.org:8080"
	@echo ""
	@echo "$(YELLOW)⚠️  Setup Required:$(NC)"
	@echo "  Add this line to your $(BOLD)/etc/hosts$(NC) file:"
	@echo "  $(BOLD)127.0.0.1    api-dev.episciences.org$(NC)"

# Show container logs
docker-logs:
	@echo "$(BOLD)Following container logs...$(NC)"
	$(DOCKER_COMPOSE) logs -f

# Show container status
docker-status:
	@echo "$(BOLD)Docker Container Status:$(NC)"
	@echo ""
	@if command -v docker >/dev/null 2>&1; then \
		echo "$(GREEN)✓ Docker is installed$(NC)"; \
	else \
		echo "$(RED)✗ Docker not found$(NC)"; \
		echo "  Install Docker: https://docs.docker.com/get-docker/"; \
		exit 1; \
	fi
	@if [ -z "$(DOCKER_COMPOSE)" ]; then \
		echo "$(RED)✗ Docker Compose not found$(NC)"; \
		echo "  Install Docker Compose: https://docs.docker.com/compose/install/"; \
		exit 1; \
	else \
		echo "$(GREEN)✓ Docker Compose is installed$(NC) ($$($(DOCKER_COMPOSE) version --short 2>/dev/null || echo "unknown version"))"; \
	fi
	@if [ -f "docker-compose.yml" ]; then \
		echo "$(GREEN)✓ docker-compose.yml found$(NC)"; \
		echo ""; \
		$(DOCKER_COMPOSE) ps; \
	else \
		echo "$(YELLOW)⚠ docker-compose.yml not found$(NC)"; \
		echo "  Create docker-compose.yml to use Docker commands"; \
	fi

# Enter PHP container shell
docker-shell:
	@echo "$(BOLD)Entering PHP container shell...$(NC)"
	$(DOCKER_COMPOSE) exec php bash

# Enter MySQL container shell
docker-mysql:
	@echo "$(BOLD)Entering MySQL container shell...$(NC)"
	$(DOCKER_COMPOSE) exec mysql mysql -uroot -proot

# Docker Testing Commands
# ========================

# Run tests in PHP container
docker-test:
	@echo "$(BOLD)Running tests in Docker container...$(NC)"
	$(DOCKER_COMPOSE) exec php vendor/bin/phpunit
	@echo "$(GREEN)✓ Docker tests completed$(NC)"

# Run tests with coverage in PHP container
docker-test-coverage:
	@echo "$(BOLD)Running tests with coverage in Docker container...$(NC)"
	$(DOCKER_COMPOSE) exec -e XDEBUG_MODE=coverage php vendor/bin/phpunit --coverage-text --coverage-html coverage/
	@echo "$(GREEN)✓ Docker tests with coverage completed$(NC)"

# Run unit tests only in PHP container
docker-test-unit:
	@echo "$(BOLD)Running unit tests in Docker container...$(NC)"
	$(DOCKER_COMPOSE) exec php vendor/bin/phpunit tests/Unit/
	@echo "$(GREEN)✓ Docker unit tests completed$(NC)"

# Install dependencies in container
docker-install:
	@echo "$(BOLD)Installing dependencies using Composer Docker image...$(NC)"
	@echo "$(BLUE)Creating Symfony directories...$(NC)"
	mkdir -p var/cache var/log
	@echo "$(BLUE)Installing composer dependencies with proper user context...$(NC)"
	docker run --rm \
		-v $(PWD):/app \
		-w /app \
		-u $(shell id -u):$(shell id -g) \
		composer:2 install --no-progress --prefer-dist --optimize-autoloader
	@echo "$(BLUE)Configuring git safe directory in PHP container...$(NC)"
	$(DOCKER_COMPOSE) exec -u root php git config --global --add safe.directory /var/www/html || true
	@echo "$(GREEN)✓ Dependencies installed securely with proper permissions$(NC)"

# Update dependencies in container
docker-composer-update:
	@echo "$(BOLD)Updating dependencies inside a temporary Composer container...$(NC)"
	docker run --rm \
		-v $(PWD):/app \
		-w /app \
		-u $(shell id -u):$(shell id -g) \
		composer:2 update --no-progress --prefer-dist --optimize-autoloader
	@echo "$(GREEN)✓ Dependencies updated and composer.lock refreshed$(NC)"

# Install dependencies optimized for CI
docker-install-ci:
	@echo "$(BOLD)Installing dependencies in PHP 8.3 container (CI optimized)...$(NC)"
	@echo "$(BLUE)Creating Symfony directories...$(NC)"
	mkdir -p var/cache var/log
	@echo "$(BLUE)Installing composer dependencies inside the PHP 8.3 container...$(NC)"
	$(DOCKER_COMPOSE) exec -T php composer install --no-progress --prefer-dist --optimize-autoloader --classmap-authoritative --no-scripts
	@echo "$(BLUE)Setting proper permissions on cache and log directories...$(NC)"
	chmod -R 775 var/cache var/log || true
	@echo "$(BLUE)Configuring git safe directory in PHP container...$(NC)"
	$(DOCKER_COMPOSE) exec -u root php git config --global --add safe.directory /var/www/html || true
	@echo "$(GREEN)✓ Dependencies installed securely with proper permissions$(NC)"

# Run composer commands in container
docker-composer:
	@if [ -z "$(CMD)" ]; then \
		echo "$(RED)Usage: make docker-composer CMD='command'$(NC)"; \
		echo "Examples:"; \
		echo "  make docker-composer CMD='install'"; \
		echo "  make docker-composer CMD='update'"; \
		echo "  make docker-composer CMD='require symfony/console'"; \
		exit 1; \
	fi
	@echo "$(BOLD)Running composer $(CMD) in Docker container...$(NC)"
	$(DOCKER_COMPOSE) exec php composer $(CMD)
	@echo "$(GREEN)✓ Composer command completed$(NC)"

# Local Development Setup Help
setup-help:
	@echo "$(BOLD)🚀 Episciences API - Local Development Setup$(NC)"
	@echo "=============================================="
	@echo ""
	@echo "$(BLUE)1. Configure /etc/hosts$(NC)"
	@echo "   Add the following line to your $(BOLD)/etc/hosts$(NC) file:"
	@echo ""
	@echo "   $(BOLD)127.0.0.1    api-dev.episciences.org$(NC)"
	@echo ""
	@echo "   $(YELLOW)How to edit /etc/hosts:$(NC)"
	@echo "   • $(BOLD)Linux/macOS:$(NC) sudo nano /etc/hosts"
	@echo "   • $(BOLD)Windows:$(NC)     Run Notepad as Administrator and open C:\\Windows\\System32\\drivers\\etc\\hosts"
	@echo ""
	@echo "$(BLUE)2. Start the application$(NC)"
	@echo "   Run: $(BOLD)make docker-up$(NC)"
	@echo ""
	@echo "$(BLUE)3. Start episciences-infrastructure first$(NC)"
	@echo "   $(BOLD)cd ../episciences-infrastructure && make up$(NC)"
	@echo ""
	@echo "$(BLUE)4. Access the application$(NC)"
	@echo "   • $(BOLD)HTTPS:$(NC) https://api-dev.episciences.org/ (via Traefik, auto-signed cert)"
	@echo "   • $(BOLD)HTTP direct:$(NC) http://api-dev.episciences.org:8080"
	@echo ""
	@echo "$(YELLOW)📝 Notes:$(NC)"
	@echo "   • HTTPS is handled by Traefik (episciences-infrastructure) with an auto-signed certificate"
	@echo "   • Accept the browser security warning on first access"
	@echo ""
	@echo "$(BLUE)4. Other useful commands$(NC)"
	@echo "   • $(BOLD)make docker-restart$(NC)  - Restart containers"
	@echo "   • $(BOLD)make docker-logs$(NC)     - View container logs"
	@echo "   • $(BOLD)make docker-status$(NC)   - Check container status"
	@echo "   • $(BOLD)make docker-shell$(NC)    - Enter PHP container"
	@echo ""
	@echo "$(GREEN)✓ Happy coding! 🎉$(NC)"

# Deployment Commands
# ===================

# Internal function to check for uncommitted changes and stash them
define check-and-stash
	@if ! git diff --quiet || ! git diff --cached --quiet; then \
		echo "$(YELLOW)⚠️  WARNING: Uncommitted changes detected!$(NC)"; \
		echo "$(YELLOW)   Stashing changes before deployment...$(NC)"; \
		git stash push -u -m "Auto-stash before deployment on $$(date)"; \
		echo "$(YELLOW)   Changes stashed. Use 'git stash pop' to restore them later.$(NC)"; \
		echo ""; \
	fi
endef

# Internal function for deployment logic
define deploy-logic
	@echo "$(BOLD)🚀 Deploying $(1)...$(NC)"
	@echo ""
	@# Check git repository
	@if [ ! -d ".git" ]; then \
		echo "$(RED)✗ Not a git repository$(NC)"; \
		exit 1; \
	fi
	@# Check and stash uncommitted changes
	$(call check-and-stash)
	@# Fetch all updates
	@echo "$(BLUE)Fetching latest changes...$(NC)"
	@git fetch --all
	@git fetch --tags
	@# Checkout and pull
	@echo "$(BLUE)Checking out $(1)...$(NC)"
	@git checkout $(1)
	@git pull
	@# Get version info
	@CURRENT_TAG=$$(git describe --tags --abbrev=0 2>/dev/null || echo "no-tag"); \
	if [ "$(1)" != "main" ]; then \
		CURRENT_TAG="$(1)"; \
	fi; \
	DEPLOY_DATE=$$(date "+%Y-%m-%d %X %z"); \
	echo "$(BLUE)Creating version.php with $$CURRENT_TAG...$(NC)"; \
	echo '<?php' > version.php; \
	echo "\$$appVersion='$$CURRENT_TAG ($$DEPLOY_DATE)';" >> version.php
	@# Install dependencies
	@echo "$(BLUE)Installing production dependencies...$(NC)"
	@if [ ! -f "composer.phar" ]; then \
		echo "$(RED)✗ composer.phar not found$(NC)"; \
		echo "  Download composer.phar first"; \
		exit 1; \
	fi
	@php8.3 composer.phar install -o --no-dev --ignore-platform-reqs
	@php8.3 composer.phar dump-env production
	@# Build assets
	@echo "$(BLUE)Building production assets...$(NC)"
	@if command -v yarn >/dev/null 2>&1; then \
		yarn; \
		yarn encore production; \
	else \
		echo "$(YELLOW)⚠️  Yarn not found, skipping asset build$(NC)"; \
	fi
	@echo ""
	@echo "$(GREEN)✅ Deployment completed successfully!$(NC)"
	@echo "$(BLUE)Deployed version:$(NC) $$(cat version.php | grep appVersion | sed "s/.*='\(.*\)';/\1/")"
endef

# Deploy main branch
deploy:
	$(call deploy-logic,main)

# Deploy specific branch
deploy-branch:
	@if [ -z "$(BRANCH)" ]; then \
		echo "$(RED)Usage: make deploy-branch BRANCH=branch-name$(NC)"; \
		echo "Examples:"; \
		echo "  make deploy-branch BRANCH=develop"; \
		echo "  make deploy-branch BRANCH=feature/new-api"; \
		exit 1; \
	fi
	$(call deploy-logic,$(BRANCH))

# Deploy specific tag
deploy-tag:
	@if [ -z "$(TAG)" ]; then \
		echo "$(RED)Usage: make deploy-tag TAG=tag-name$(NC)"; \
		echo "Examples:"; \
		echo "  make deploy-tag TAG=v1.0.0"; \
		echo "  make deploy-tag TAG=v2.1.3"; \
		exit 1; \
	fi
	$(call deploy-logic,$(TAG))