
FROM php:8.2-fpm-alpine


RUN apk add --no-cache \
    nginx \
    git \
    curl \
    supervisor \
    nodejs \
    npm \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libwebp-dev \
    libxpm-dev \
    sqlite-dev \
    mysql-dev \
    postgresql-dev \
    oniguruma-dev \
    autoconf \
    gcc \
    g++ \
    make \
    bash


RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp


RUN docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    pdo_sqlite \
    mysqli \
    zip \
    gd \
    exif \
    bcmath \
    pcntl \
    mbstring


ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PATH="/root/.composer/vendor/bin:$PATH"
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer


WORKDIR /app


COPY . /app


RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache


RUN composer install --no-dev --optimize-autoloader


RUN npm ci --only=production
RUN npm run build


COPY nginx.conf /etc/nginx/nginx.conf


RUN mkdir -p /etc/supervisor/conf.d
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf


RUN mkdir -p /var/log/supervisor \
    && mkdir -p /var/log/nginx \
    && mkdir -p /run/nginx


COPY start.sh /app/start.sh
RUN chmod +x /app/start.sh \
    && dos2unix /app/start.sh 2>/dev/null || true


EXPOSE 80


CMD ["/app/start.sh"]