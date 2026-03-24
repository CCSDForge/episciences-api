#!/bin/bash

# Ensure we're in the project root
cd "$(dirname "$0")/.."

echo "Running PHPUnit tests with HTML coverage..."

# Create coverage directory if it doesn't exist
mkdir -p coverage/
chmod 777 coverage/

# Check if Docker container is running
DOCKER_COMPOSE=$(command -v docker-compose || echo "docker compose")
if $DOCKER_COMPOSE ps --format '{{.Service}}' | grep -q "php"; then
    echo "Docker container detected, running coverage inside Docker..."
    $DOCKER_COMPOSE exec -e XDEBUG_MODE=coverage php vendor/bin/phpunit --coverage-html coverage/
else
    echo "Docker not running, trying local PHP..."
    # Check if Xdebug or PCOV is available
    if ! php8.2 -m | grep -qE "xdebug|pcov"; then
        echo "Error: Neither Xdebug nor PCOV detected in local PHP (php8.2)."
        echo "Please start Docker with 'make docker-up' or install Xdebug locally."
        exit 1
    fi
    # Run tests with coverage
    XDEBUG_MODE=coverage php8.2 vendor/bin/phpunit --coverage-html coverage/
fi

if [ -f "coverage/index.html" ]; then
    echo ""
    echo "Coverage report generated in coverage/index.html"
else
    echo ""
    echo "Error: Coverage report was not generated."
    exit 1
fi
