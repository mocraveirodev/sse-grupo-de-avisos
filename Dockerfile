# FrankenPHP = PHP + servidor web (Caddy) num binário só.
# Cada conexão SSE usa uma thread leve em vez de um worker inteiro do PHP-FPM.
FROM dunglas/frankenphp:1-php8.4-alpine

# Extensão do Redis (phpredis), com versão fixa e baixada direto do pacote do PECL.
# O "install-php-extensions redis" sem versão consulta o índice do PECL, que às vezes
# fica incompleto para a versão mais nova ("does not have REST xml available").
# Se o download direto falhar, tenta pelo nome com a versão fixa.
ARG PHPREDIS_VERSION=6.2.0
RUN (install-php-extensions "https://pecl.php.net/get/redis-${PHPREDIS_VERSION}.tgz" \
      || install-php-extensions "redis-${PHPREDIS_VERSION}") \
    && php --ri redis > /dev/null

COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-sse.ini
COPY docker/Caddyfile /etc/frankenphp/Caddyfile

WORKDIR /app
COPY src ./src
COPY public ./public
COPY bin ./bin

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
