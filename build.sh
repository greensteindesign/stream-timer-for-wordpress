#!/usr/bin/env bash
#
# Build a WordPress.org-ready ZIP of the plugin.
# Output: dist/<slug>-<version>.zip
#
# Usage:
#   ./build.sh
#
# Requires Docker (for wp-cli i18n make-pot / make-mo regeneration).
# If --skip-i18n is passed, existing .mo files in languages/ are used as-is.

set -euo pipefail

SLUG="stream-timer"
ROOT="$(cd "$(dirname "$0")" && pwd)"
MAIN_FILE="$ROOT/stream-timer-for-wordpress.php"
DIST_DIR="$ROOT/dist"
LANG_DIR="$ROOT/languages"

if [[ ! -f "$MAIN_FILE" ]]; then
    echo "ERROR: main plugin file not found at $MAIN_FILE" >&2
    exit 1
fi

VERSION=$(grep -E "^[[:space:]]*\*[[:space:]]*Version:" "$MAIN_FILE" | head -1 | sed -E 's/.*Version:[[:space:]]*([^[:space:]]+).*/\1/')
if [[ -z "${VERSION:-}" ]]; then
    echo "ERROR: could not extract Version from $MAIN_FILE" >&2
    exit 1
fi

echo ">> Building ${SLUG} v${VERSION}"

SKIP_I18N=0
for arg in "$@"; do
    case "$arg" in
        --skip-i18n) SKIP_I18N=1 ;;
    esac
done

# Step 1: regenerate .pot + .mo via wp-cli (in Docker) unless skipped
if [[ $SKIP_I18N -eq 0 ]]; then
    if command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1; then
        echo ">> Regenerating .pot and .mo via wp-cli (Docker)"
        docker run --rm \
            -v "$ROOT":/plugin \
            -w /plugin \
            --user "$(id -u):$(id -g)" \
            -e HOME=/tmp \
            wordpress:cli-php8.2 \
            sh -c "php -d memory_limit=512M /usr/local/bin/wp i18n make-pot . languages/${SLUG}.pot --slug=${SLUG} && php -d memory_limit=512M /usr/local/bin/wp i18n make-mo languages"
    else
        echo "WARN: Docker not available — keeping existing .mo files in languages/" >&2
    fi
fi

# Step 2: stage files into dist/<slug>/
STAGE="$DIST_DIR/${SLUG}"
rm -rf "$STAGE"
mkdir -p "$STAGE"

# Whitelist of files/dirs to include
INCLUDES=(
    "stream-timer-for-wordpress.php"
    "uninstall.php"
    "readme.txt"
    "README.md"
    "LICENSE"
    "assets"
    "languages"
)

for item in "${INCLUDES[@]}"; do
    if [[ -e "$ROOT/$item" ]]; then
        cp -R "$ROOT/$item" "$STAGE/"
    fi
done

# Strip any stray dev artifacts from the staged copy
find "$STAGE" -name ".DS_Store" -delete
find "$STAGE" -name "*.swp" -delete
find "$STAGE" -name "Thumbs.db" -delete

# Step 3: zip it
ZIP_NAME="${SLUG}-${VERSION}.zip"
ZIP_PATH="$DIST_DIR/$ZIP_NAME"
rm -f "$ZIP_PATH"

( cd "$DIST_DIR" && zip -rq "$ZIP_NAME" "$SLUG" )

# Step 4: cleanup stage dir, keep only the zip
rm -rf "$STAGE"

SIZE=$(du -h "$ZIP_PATH" | cut -f1)
echo ">> OK: $ZIP_PATH ($SIZE)"
