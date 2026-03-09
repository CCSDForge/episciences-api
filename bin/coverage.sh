#!/bin/bash

# Ensure we're in the project root
cd "$(dirname "$0")/.."

echo "Running PHPUnit tests with HTML coverage..."

# Create coverage directory if it doesn't exist
mkdir -p coverage/

# Check if Xdebug or PCOV is available
if ! php8.2 -m | grep -qE "xdebug|pcov"; then
    echo "Warning: Neither Xdebug nor PCOV detected. Coverage might fail or be incomplete."
fi

# Run tests with coverage
XDEBUG_MODE=coverage php8.2 vendor/bin/phpunit --coverage-html coverage/

echo ""
echo "Coverage report generated in coverage/index.html"
