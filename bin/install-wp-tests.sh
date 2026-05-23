#!/usr/bin/env bash
#
# install-wp-tests.sh
#
# Sets up the WordPress test environment for PHPUnit. Downloads WordPress core
# to $WP_CORE_DIR (default /tmp/wordpress/) and creates the test database.
#
# The WP test suite itself lives at vendor/wp-phpunit/wp-phpunit (composer
# dep) — this script only handles WP core + DB.
#
# Usage:
#   bin/install-wp-tests.sh <db_name> <db_user> <db_pass> [db_host] [wp_version] [skip-db-create]
#
# Examples:
#   bin/install-wp-tests.sh wordpress_test root '' localhost latest
#   WP_CORE_DIR=/custom/path bin/install-wp-tests.sh wordpress_test root '' localhost 6.4
#

if [ $# -lt 3 ]; then
    echo "usage: $0 <db_name> <db_user> <db_pass> [db_host] [wp_version] [skip-db-create]"
    exit 1
fi

DB_NAME="$1"
DB_USER="$2"
DB_PASS="$3"
DB_HOST="${4:-localhost}"
WP_VERSION="${5:-latest}"
SKIP_DB_CREATE="${6:-false}"

WP_CORE_DIR="${WP_CORE_DIR:-/tmp/wordpress/}"

set -ex

download() {
    if command -v curl >/dev/null 2>&1; then
        curl -fsL "$1" > "$2"
    elif command -v wget >/dev/null 2>&1; then
        wget -nv -O "$2" "$1"
    else
        echo "neither curl nor wget installed" >&2
        exit 1
    fi
}

# ── 1. Resolve WP version ─────────────────────────────────────────────────
if [[ "$WP_VERSION" == "latest" ]]; then
    download https://api.wordpress.org/core/version-check/1.7/ /tmp/wp-latest.json
    WP_VERSION=$(grep -o '"version":"[^"]*' /tmp/wp-latest.json | head -1 | sed 's/"version":"//')
    if [[ -z "$WP_VERSION" ]]; then
        echo "could not resolve latest WP version" >&2
        exit 1
    fi
fi

ARCHIVE_URL="https://wordpress.org/wordpress-${WP_VERSION}.tar.gz"

# ── 2. Download WP core ───────────────────────────────────────────────────
if [[ ! -d "$WP_CORE_DIR" ]]; then
    mkdir -p "$WP_CORE_DIR"
    download "$ARCHIVE_URL" /tmp/wordpress.tar.gz
    tar --strip-components=1 -zxf /tmp/wordpress.tar.gz -C "$WP_CORE_DIR"
fi

# Ensure WP can write to a real wp-content/uploads during tests
mkdir -p "$WP_CORE_DIR/wp-content/uploads"

# ── 3. Create test database ───────────────────────────────────────────────
if [[ "$SKIP_DB_CREATE" != "true" ]]; then
    if [[ -z "$DB_PASS" ]]; then
        MYSQL_AUTH=( -u"$DB_USER" )
    else
        MYSQL_AUTH=( -u"$DB_USER" -p"$DB_PASS" )
    fi

    if [[ "$DB_HOST" == "localhost" || "$DB_HOST" == "127.0.0.1" ]]; then
        SOCKET_OPT=()
    else
        SOCKET_OPT=( -h"$DB_HOST" )
    fi

    mysqladmin "${MYSQL_AUTH[@]}" "${SOCKET_OPT[@]}" create "$DB_NAME" --force 2>/dev/null || true
fi

echo "✓ WP core at $WP_CORE_DIR"
echo "✓ Test DB '$DB_NAME' ready"
