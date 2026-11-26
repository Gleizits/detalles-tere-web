# ------------------------------------------------------------
# DetallesTerePHP — imagen para Render (PHP 8.2 + Apache)
#
# Render no tiene runtime PHP nativo: se despliega como Docker.
# La base sigue siendo SQL Server 2019 Developer (DetallesTereDB),
# accesible desde el contenedor mediante DB_SERVER / DB_PORT.
# ------------------------------------------------------------
FROM php:8.2-apache-bookworm

ENV ACCEPT_EULA=Y
ENV DEBIAN_FRONTEND=noninteractive

# 1. Librerías del sistema, ODBC Driver 18 y extensiones PHP para SQL Server.
#    msodbcsql18 es imprescindible en tiempo de ejecución: pdo_sqlsrv lo carga.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates curl gnupg2 unixodbc-dev libonig-dev libssl-dev \
    && curl -fsSL https://packages.microsoft.com/keys/microsoft.asc \
        | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && curl -fsSL https://packages.microsoft.com/config/debian/12/prod.list \
        > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y --no-install-recommends msodbcsql18 \
    && docker-php-ext-install mbstring \
    && pecl install sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 2. Apache: mod_rewrite para el front controller, DocumentRoot en public/.
RUN a2enmod rewrite headers \
    && printf '%s\n' \
        '<VirtualHost *:80>' \
        '    ServerName localhost' \
        '    DocumentRoot /var/www/html/public' \
        '    <Directory /var/www/html/public>' \
        '        Options -Indexes -MultiViews' \
        '        AllowOverride All' \
        '        Require all granted' \
        '    </Directory>' \
        '    ErrorLog /proc/self/fd/2' \
        '    CustomLog /proc/self/fd/1 combined' \
        '</VirtualHost>' \
        > /etc/apache2/sites-available/000-default.conf

# 3. Código de la aplicación (el contexto de build es DetallesTerePHP/).
WORKDIR /var/www/html
COPY . .

RUN mkdir -p var/log \
    && chown -R www-data:www-data /var/www/html

# 4. Producción: cookie Secure y sin trazas en pantalla (se puede sobrescribir).
ENV APP_PRODUCTION=true \
    COOKIE_SECURE=true \
    APP_TIMEZONE=America/Lima \
    DB_TRUST_CERT=true

EXPOSE 80

CMD ["apache2-foreground"]
