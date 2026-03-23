#!/usr/bin/env bash

set -e 

echo "Building Easy Symbols & Icons Plugin..."

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PROJECT_ROOT"

echo "Working directory: $(pwd)"

echo "Installing PHP dependencies (Composer)..."
if command -v composer >/dev/null 2>&1; then
    composer install --no-dev --no-interaction --prefer-dist
else
    echo "Composer not found. Please install Composer."
    exit 1
fi

echo "Regenerating Composer autoload..."
composer dump-autoload -o

BLOCK_DIR="$PROJECT_ROOT/src/blocks/eics-icon"

if [ -d "$BLOCK_DIR" ]; then
    echo "Installing Node dependencies for eics-icon..."
    cd "$BLOCK_DIR"

    if command -v npm >/dev/null 2>&1; then
        npm install
    else
        echo "npm not found. Please install Node.js."
        exit 1
    fi

    echo "Building block assets..."
    if npm run | grep -q "build"; then
        npm run build
    else
        echo "No build script found, trying default wp-scripts build..."
        npx wp-scripts build
    fi

    cd "$PROJECT_ROOT"
else
    echo "Block directory $BLOCK_DIR not found, skipping..."
fi

echo "Cleaning up..."
if [ -d "$BLOCK_DIR/node_modules" ]; then
    rm -rf "$BLOCK_DIR/node_modules"
fi

echo "Build complete!"