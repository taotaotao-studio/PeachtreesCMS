#!/bin/sh
# PeachtreesCMS container entrypoint
#  1. generates pt_api/.env from environment variables on first start
#  2. populates a fresh content/ volume with the default themes/patterns
#  3. ensures runtime directories are writable
set -e

ENV_FILE=/var/www/html/pt_api/.env

# --- 1. first-start .env bootstrap ------------------------------------------
# config.php reads config from .env; in the container the database connection
# always comes from the environment. The session dir is moved OUTSIDE the web
# root (strongest protection, see config.php).
if [ ! -f "$ENV_FILE" ]; then
    {
        echo "# PeachtreesCMS container environment (generated at first start)"
        echo ""
        echo "# Database"
        echo "DB_HOST=${DB_HOST:-db}"
        echo "DB_NAME=${DB_NAME:-peachtrees}"
        echo "DB_USER=${DB_USER:-root}"
        echo "DB_PASS=${DB_PASS:-}"
        echo ""
        echo "# Sessions live outside the web root"
        echo "SESSION_DIR=/var/www/sessions"
        echo ""
        echo "# Update source override (optional)"
        if [ -n "$UPDATE_REPO" ]; then
            echo "UPDATE_REPO=${UPDATE_REPO}"
        fi
    } > "$ENV_FILE"
    chown www-data:www-data "$ENV_FILE"
fi

# --- 2. populate a fresh content/ volume ------------------------------------
mkdir -p /var/www/html/content
if [ ! -f /var/www/html/content/.initialized ]; then
    # -n: never overwrite user data already present in the volume
    cp -an /opt/pt-content-default/. /var/www/html/content/
    touch /var/www/html/content/.initialized
    chown -R www-data:www-data /var/www/html/content
fi

# --- 3. runtime dirs ---------------------------------------------------------
mkdir -p /var/www/sessions /var/www/html/content/uploads
chown -R www-data:www-data /var/www/html/content /var/www/sessions

exec apache2-foreground
