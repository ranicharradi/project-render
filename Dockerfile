FROM php:8.2-cli

WORKDIR /app
COPY . .

# If you later add dependencies via Composer, this enables Docker builds to install them.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader --no-interaction || true

ENV PORT=10000
EXPOSE 10000

CMD ["sh", "-lc", "php -S 0.0.0.0:${PORT} -t public"]

