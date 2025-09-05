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
.PHONY: help check-prereqs install ssl-certs ssl-clean test test-unit test-coverage test-file validate clean docker-up docker-down docker-restart docker-logs docker-status docker-shell docker-mysql docker-test docker-test-coverage docker-test-unit docker-install docker-install-ci docker-composer

# Help target - displays all available commands
help:
	@echo "$(BOLD)Episciences API - Development Commands$(NC)"
	@echo "======================================"
	@echo ""
	@echo "$(BLUE)Setup Commands:$(NC)"
	@echo "  $(BOLD)check-prereqs$(NC)     Check if all prerequisites are installed"
	@echo "  $(BOLD)install$(NC)           Install PHP dependencies"
	@echo "  $(BOLD)ssl-certs$(NC)         Generate SSL certificates for HTTPS development"
	@echo "  $(BOLD)ssl-clean$(NC)         Remove SSL certificates"
	@echo ""
	@echo "$(BLUE)Testing Commands:$(NC)"
	@echo "  $(BOLD)test$(NC)              Run all PHPUnit tests"
	@echo "  $(BOLD)test-unit$(NC)         Run only unit tests"
	@echo "  $(BOLD)test-coverage$(NC)     Run tests with coverage report"
	@echo "  $(BOLD)test-file$(NC)         Run specific test file (usage: make test-file FILE=path/to/TestFile.php)"
	@echo ""
	@echo "$(BLUE)Utility Commands:$(NC)"
	@echo "  $(BOLD)validate$(NC)          Check PHP syntax of test files"
	@echo "  $(BOLD)clean$(NC)             Clean cache and temporary files"
	@echo ""
	@echo "$(BLUE)Docker Development Commands:$(NC)"
	@echo "  $(BOLD)docker-up$(NC)         Start all containers (detached)"
	@echo "  $(BOLD)docker-down$(NC)       Stop all containers"
	@echo "  $(BOLD)docker-restart$(NC)    Restart all containers"
	@echo "  $(BOLD)docker-logs$(NC)       Follow container logs"
	@echo "  $(BOLD)docker-status$(NC)     Show container status"
	@echo "  $(BOLD)docker-shell$(NC)      Enter PHP container shell"
	@echo "  $(BOLD)docker-mysql$(NC)      Enter MySQL container shell"
	@echo ""
	@echo "$(BLUE)Docker Testing Commands:$(NC)"
	@echo "  $(BOLD)docker-test$(NC)       Run tests in PHP container"
	@echo "  $(BOLD)docker-test-coverage$(NC) Run tests with coverage in PHP container"
	@echo "  $(BOLD)docker-test-unit$(NC)  Run unit tests only in PHP container"
	@echo "  $(BOLD)docker-install$(NC)    Install dependencies in container"
	@echo "  $(BOLD)docker-install-ci$(NC) Install dependencies (CI optimized)"
	@echo "  $(BOLD)docker-composer$(NC)   Run composer in container (usage: make docker-composer CMD='update')"
	@echo ""
	@echo "$(YELLOW)Prerequisites (Local Development):$(NC)"
	@echo "  - PHP 8.2+ (php8.2 command)"
	@echo "  - Local composer binary (./composer)"
	@echo "  - OpenSSL (for SSL certificate generation)"
	@echo "  - Installed dependencies (vendor/)"
	@echo ""
	@echo "$(YELLOW)Prerequisites (Docker Development):$(NC)"
	@echo "  - Docker and Docker Compose installed"
	@echo "  - docker-compose.yml file configured"
	@echo ""
	@echo "Run '$(BOLD)make check-prereqs$(NC)' to verify local setup or '$(BOLD)make docker-status$(NC)' to check Docker."

# Check all prerequisites
check-prereqs:
	@echo "$(BOLD)Checking Prerequisites...$(NC)"
	@echo ""
	@# Check PHP 8.2
	@if ! command -v php8.2 >/dev/null 2>&1; then \
		echo "$(RED)✗ PHP 8.2 not found$(NC)"; \
		echo "  Install PHP 8.2:"; \
		echo "    Ubuntu/Debian: $(BOLD)sudo apt install php8.2 php8.2-cli php8.2-mbstring php8.2-xml php8.2-mysql$(NC)"; \
		echo "    CentOS/RHEL:   $(BOLD)sudo yum install php82 php82-cli php82-mbstring php82-xml php82-mysqlnd$(NC)"; \
		echo ""; \
		exit 1; \
	else \
		echo "$(GREEN)✓ PHP 8.2 found$(NC) ($$(php8.2 --version | head -n1))"; \
	fi
	@# Check local composer
	@if [ ! -f "./composer" ]; then \
		echo "$(RED)✗ Local composer not found$(NC)"; \
		echo "  Download composer:"; \
		echo "    $(BOLD)curl -sS https://getcomposer.org/installer | php8.2$(NC)"; \
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
	@if [ ! -f "vendor/bin/simple-phpunit" ]; then \
		echo "$(YELLOW)⚠ PHPUnit not found$(NC)"; \
		echo "  Install dependencies: $(BOLD)make install$(NC)"; \
		echo ""; \
	else \
		echo "$(GREEN)✓ PHPUnit available$(NC)"; \
	fi
	@# Check OpenSSL
	@if ! command -v openssl >/dev/null 2>&1; then \
		echo "$(YELLOW)⚠ OpenSSL not found$(NC)"; \
		echo "  Install OpenSSL:"; \
		echo "    Ubuntu/Debian: $(BOLD)sudo apt install openssl$(NC)"; \
		echo "    CentOS/RHEL:   $(BOLD)sudo yum install openssl$(NC)"; \
		echo "    macOS:         $(BOLD)brew install openssl$(NC)"; \
		echo ""; \
	else \
		echo "$(GREEN)✓ OpenSSL available$(NC)"; \
	fi
	@echo ""
	@echo "$(GREEN)$(BOLD)✓ All prerequisites met!$(NC)"

# Install PHP dependencies
install: check-prereqs
	@echo "$(BOLD)Installing PHP dependencies...$(NC)"
	php8.2 ./composer install --no-progress --prefer-dist --optimize-autoloader
	@echo "$(GREEN)✓ Dependencies installed successfully$(NC)"

# Generate SSL certificates for HTTPS development
ssl-certs:
	@echo "$(BOLD)Generating SSL certificates for development...$(NC)"
	@if ! command -v openssl >/dev/null 2>&1; then \
		echo "$(RED)✗ OpenSSL not found$(NC)"; \
		echo "  Install OpenSSL:"; \
		echo "    Ubuntu/Debian: $(BOLD)sudo apt install openssl$(NC)"; \
		echo "    CentOS/RHEL:   $(BOLD)sudo yum install openssl$(NC)"; \
		echo "    macOS:         $(BOLD)brew install openssl$(NC)"; \
		exit 1; \
	fi
	@mkdir -p docker/apache/ssl
	@echo "[req]" > docker/apache/ssl/openssl.conf
	@echo "default_bits = 2048" >> docker/apache/ssl/openssl.conf
	@echo "prompt = no" >> docker/apache/ssl/openssl.conf
	@echo "distinguished_name = req_distinguished_name" >> docker/apache/ssl/openssl.conf
	@echo "req_extensions = v3_req" >> docker/apache/ssl/openssl.conf
	@echo "" >> docker/apache/ssl/openssl.conf
	@echo "[req_distinguished_name]" >> docker/apache/ssl/openssl.conf
	@echo "C = FR" >> docker/apache/ssl/openssl.conf
	@echo "ST = France" >> docker/apache/ssl/openssl.conf
	@echo "L = Lyon" >> docker/apache/ssl/openssl.conf
	@echo "O = Episciences" >> docker/apache/ssl/openssl.conf
	@echo "OU = Development" >> docker/apache/ssl/openssl.conf
	@echo "CN = api-dev.episciences.org" >> docker/apache/ssl/openssl.conf
	@echo "emailAddress = dev@episciences.org" >> docker/apache/ssl/openssl.conf
	@echo "" >> docker/apache/ssl/openssl.conf
	@echo "[v3_req]" >> docker/apache/ssl/openssl.conf
	@echo "keyUsage = keyEncipherment, dataEncipherment, digitalSignature" >> docker/apache/ssl/openssl.conf
	@echo "extendedKeyUsage = serverAuth" >> docker/apache/ssl/openssl.conf
	@echo "subjectAltName = @alt_names" >> docker/apache/ssl/openssl.conf
	@echo "" >> docker/apache/ssl/openssl.conf
	@echo "[alt_names]" >> docker/apache/ssl/openssl.conf
	@echo "DNS.1 = api-dev.episciences.org" >> docker/apache/ssl/openssl.conf
	@echo "DNS.2 = localhost" >> docker/apache/ssl/openssl.conf
	@echo "IP.1 = 127.0.0.1" >> docker/apache/ssl/openssl.conf
	@if [ ! -f "docker/apache/ssl/api-dev.episciences.org.crt" ]; then \
		openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
			-keyout docker/apache/ssl/api-dev.episciences.org.key \
			-out docker/apache/ssl/api-dev.episciences.org.crt \
			-config docker/apache/ssl/openssl.conf \
			-extensions v3_req; \
		echo "$(GREEN)✓ SSL certificates generated$(NC)"; \
	else \
		echo "$(YELLOW)⚠ SSL certificates already exist$(NC)"; \
		echo "  Run 'make ssl-clean ssl-certs' to regenerate"; \
	fi

# Clean SSL certificates
ssl-clean:
	@echo "$(BOLD)Cleaning SSL certificates...$(NC)"
	@rm -rf docker/apache/ssl/
	@echo "$(GREEN)✓ SSL certificates cleaned$(NC)"

# Run all tests
test: check-prereqs
	@if [ ! -d "vendor" ]; then \
		echo "$(RED)Dependencies not installed. Run: make install$(NC)"; \
		exit 1; \
	fi
	@echo "$(BOLD)Running all PHPUnit tests...$(NC)"
	@if [ ! -f "phpunit-9.5-0/phpunit" ]; then \
		echo "$(YELLOW)PHPUnit not installed. Installing...$(NC)"; \
		COMPOSER=$(PWD)/composer PATH=$(PWD):$(PATH) php8.2 vendor/bin/simple-phpunit --version >/dev/null 2>&1; \
	fi
	php8.2 phpunit-9.5-0/phpunit
	@echo "$(GREEN)✓ Tests completed$(NC)"

# Run only unit tests
test-unit: check-prereqs
	@if [ ! -d "vendor" ]; then \
		echo "$(RED)Dependencies not installed. Run: make install$(NC)"; \
		exit 1; \
	fi
	@echo "$(BOLD)Running unit tests...$(NC)"
	@if [ ! -f "phpunit-9.5-0/phpunit" ]; then \
		echo "$(YELLOW)PHPUnit not installed. Installing...$(NC)"; \
		COMPOSER=$(PWD)/composer PATH=$(PWD):$(PATH) php8.2 vendor/bin/simple-phpunit --version >/dev/null 2>&1; \
	fi
	php8.2 phpunit-9.5-0/phpunit tests/Unit/
	@echo "$(GREEN)✓ Unit tests completed$(NC)"

# Run tests with coverage
test-coverage: check-prereqs
	@if [ ! -d "vendor" ]; then \
		echo "$(RED)Dependencies not installed. Run: make install$(NC)"; \
		exit 1; \
	fi
	@echo "$(BOLD)Running tests with coverage...$(NC)"
	@if [ ! -f "phpunit-9.5-0/phpunit" ]; then \
		echo "$(YELLOW)PHPUnit not installed. Installing...$(NC)"; \
		COMPOSER=$(PWD)/composer PATH=$(PWD):$(PATH) php8.2 vendor/bin/simple-phpunit --version >/dev/null 2>&1; \
	fi
	php8.2 phpunit-9.5-0/phpunit --coverage-text --coverage-html coverage/
	@echo "$(GREEN)✓ Tests with coverage completed$(NC)"
	@echo "Coverage report available at: $(BOLD)coverage/index.html$(NC)"

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
	@if [ ! -f "phpunit-9.5-0/phpunit" ]; then \
		echo "$(YELLOW)PHPUnit not installed. Installing...$(NC)"; \
		COMPOSER=$(PWD)/composer PATH=$(PWD):$(PATH) php8.2 vendor/bin/simple-phpunit --version >/dev/null 2>&1; \
	fi
	php8.2 phpunit-9.5-0/phpunit $(FILE)
	@echo "$(GREEN)✓ Test file completed$(NC)"

# Validate PHP syntax of test files
validate:
	@echo "$(BOLD)Validating PHP syntax of test files...$(NC)"
	@find tests/ -name "*.php" -exec php8.2 -l {} \; | grep -v "No syntax errors detected" || true
	@echo "$(GREEN)✓ PHP syntax validation completed$(NC)"

# Clean cache and temporary files
clean:
	@echo "$(BOLD)Cleaning cache and temporary files...$(NC)"
	@rm -rf var/cache/*
	@rm -rf coverage/
	@echo "$(GREEN)✓ Cleanup completed$(NC)"

# Docker Development Commands
# ===========================

# Start all containers in detached mode
docker-up: ssl-certs
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

# Stop all containers
docker-down:
	@echo "$(BOLD)Stopping Docker containers...$(NC)"
	$(DOCKER_COMPOSE) down
	@echo "$(GREEN)✓ Containers stopped$(NC)"

# Restart all containers
docker-restart:
	@echo "$(BOLD)Restarting Docker containers...$(NC)"
	$(DOCKER_COMPOSE) restart
	@echo "$(GREEN)✓ Containers restarted$(NC)"

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
	$(DOCKER_COMPOSE) exec php vendor/bin/simple-phpunit
	@echo "$(GREEN)✓ Docker tests completed$(NC)"

# Run tests with coverage in PHP container
docker-test-coverage:
	@echo "$(BOLD)Running tests with coverage in Docker container...$(NC)"
	$(DOCKER_COMPOSE) exec -e XDEBUG_MODE=coverage php vendor/bin/simple-phpunit --coverage-clover=coverage.xml
	@echo "$(GREEN)✓ Docker tests with coverage completed$(NC)"

# Run unit tests only in PHP container
docker-test-unit:
	@echo "$(BOLD)Running unit tests in Docker container...$(NC)"
	$(DOCKER_COMPOSE) exec php vendor/bin/simple-phpunit tests/Unit/
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
		composer:latest install --no-progress --prefer-dist --optimize-autoloader
	@echo "$(BLUE)Configuring git safe directory in PHP container...$(NC)"
	$(DOCKER_COMPOSE) exec -u root php git config --global --add safe.directory /var/www/html || true
	@echo "$(GREEN)✓ Dependencies installed securely with proper permissions$(NC)"

# Install dependencies optimized for CI
docker-install-ci:
	@echo "$(BOLD)Installing dependencies using Composer Docker image (CI optimized)...$(NC)"
	@echo "$(BLUE)Creating Symfony directories...$(NC)"
	mkdir -p var/cache var/log
	@echo "$(BLUE)Installing composer dependencies with proper user context...$(NC)"
	docker run --rm \
		-v $(PWD):/app \
		-w /app \
		-u $(shell id -u):$(shell id -g) \
		composer:latest install --no-progress --prefer-dist --optimize-autoloader --classmap-authoritative --no-scripts
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