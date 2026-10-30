FROM php:8.3-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends \
    chromium chromium-driver fonts-liberation python3 python3-venv ca-certificates \
    libonig-dev libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring intl zip gd \
    && a2enmod rewrite headers expires deflate \
    && rm -rf /var/lib/apt/lists/*
RUN python3 -m venv /opt/venv
COPY requirements.txt /tmp/requirements.txt
RUN /opt/venv/bin/pip install --no-cache-dir -r /tmp/requirements.txt
ENV PYTHON_BIN=/opt/venv/bin/python \
    CHROME_BIN=/usr/bin/chromium \
    CHROMEDRIVER_BIN=/usr/bin/chromedriver \
    XDG_CACHE_HOME=/var/www/html/storage/browser/cache \
    XDG_CONFIG_HOME=/var/www/html/storage/browser/config
WORKDIR /var/www/html
COPY . /var/www/html
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-gmap.ini
COPY docker/entrypoint.sh /usr/local/bin/gmap-entrypoint
RUN chmod +x /usr/local/bin/gmap-entrypoint \
    && mkdir -p storage/sessions storage/browser \
    && chown -R www-data:www-data storage \
    && php scripts/check_runtime.php
EXPOSE 80
ENTRYPOINT ["gmap-entrypoint"]
CMD ["apache2-foreground"]
