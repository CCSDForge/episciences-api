# Episciences API - Development Makefile
# =====================================

# Colors for output
RED=\033[0;31m
GREEN=\033[0;32m
YELLOW=\033[0;33m
BLUE=\033[0;34m
BOLD=\033[1m
NC=\033[0m # No Color

# Default target
.DEFAULT_GOAL := help

# Phony targets
.PHONY: help check-prereqs install test test-unit test-coverage test-file validate clean

# Help target - displays all available commands
help:
	@echo "$(BOLD)Episciences API - Development Commands$(NC)"
	@echo "======================================"
	@echo ""
	@echo "$(BLUE)Setup Commands:$(NC)"
	@echo "  $(BOLD)check-prereqs$(NC)     Check if all prerequisites are installed"
	@echo "  $(BOLD)install$(NC)           Install PHP dependencies"
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
	@echo "$(YELLOW)Prerequisites:$(NC)"
	@echo "  - PHP 8.2+ (php8.2 command)"
	@echo "  - Local composer binary (./composer)"
	@echo "  - Installed dependencies (vendor/)"
	@echo ""
	@echo "Run '$(BOLD)make check-prereqs$(NC)' to verify your setup."

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
	@echo ""
	@echo "$(GREEN)$(BOLD)✓ All prerequisites met!$(NC)"

# Install PHP dependencies
install: check-prereqs
	@echo "$(BOLD)Installing PHP dependencies...$(NC)"
	php8.2 ./composer install --no-progress --prefer-dist --optimize-autoloader
	@echo "$(GREEN)✓ Dependencies installed successfully$(NC)"

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