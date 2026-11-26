# PeachtreesCMS — production image
#
# Multi-stage build:
#   stage 1 (node) compiles the React frontend;
#   stage 2 (php:8.2-apache) serves the compiled assets + PHP backend.
#
# The Apache base is deliberate: the security rules in .htaccess (dotfile deny,
# pt_api/sessions deny) work out of the box with zero extra server config.
#
# Build:  docker build -t peachtrees-cms .
# Run:    see docker/docker-compose.yml

# ---------------------------------------------------------------- stage 1: frontend
FROM node:22-alpine AS frontend-build

WORKDIR /build
RUN npm install -g pnpm

COPY pt_frontend/package.json pt_frontend/pnpm-lock.yaml ./
RUN pnpm install

COPY pt_frontend/ ./
RUN pnpm build

# ---------------------------------------------------------------- stage 2: runtime
FROM php:8.2-apache

# pdo_mysql is required by the CMS; gd renders the comment captcha; zip
# (ZipArchive) powers the online updater; rewrite is required by .htaccess rules
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg-dev libfreetype6-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql gd zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Frontend build output (index.html, admin.html, assets/, languages/, imgs/,
# content/themes, content/patterns, ...)
COPY --from=frontend-build /build/dist/ /var/www/html/

# Backend + root .htaccess
COPY pt_api/ /var/www/html/pt_api/
COPY .htaccess /var/www/html/.htaccess

# Local state must never ship inside an image (.dockerignore already excludes
# them; belt-and-braces here so a modified context cannot leak secrets).
RUN rm -f /var/www/html/pt_api/.env \
            /var/www/html/pt_api/.env.local \
            /var/www/html/pt_api/.installed \
    && rm -rf /var/www/html/pt_api/sessions

# Pristine copy of the default content tree (themes/patterns/uploads scaffold).
# Kept OUTSIDE the web root so a fresh named volume can be populated from it
# on first start without the mount shadowing the image files.
RUN cp -a /var/www/html/content /opt/pt-content-default

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p /var/www/sessions \
    && chown -R www-data:www-data /var/www/html /var/www/sessions

# Only user content is stateful — everything else is disposable code that
# upgrades replace wholesale.
VOLUME /var/www/html/content

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
