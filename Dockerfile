# FrankenPHP = PHP + servidor web (Caddy) num binário só.
# Cada conexão SSE usa uma thread leve em vez de um worker inteiro do PHP-FPM.
FROM dunglas/frankenphp:1-php8.4-alpine

# Extensão do Redis (o install-php-extensions já vem na imagem).
RUN install-php-extensions redis

COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-sse.ini
COPY docker/Caddyfile /etc/frankenphp/Caddyfile

WORKDIR /app
COPY src ./src
COPY public ./public
COPY bin ./bin

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
