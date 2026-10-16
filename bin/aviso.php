<?php

declare(strict_types=1);

/*
 * Manda um aviso direto pelo Redis, sem passar pelo servidor web.
 * Serve para o "Ato 3" da demo: o servidor web está fora do ar, os avisos
 * continuam entrando, e quando ele volta os navegadores recuperam tudo
 * com o Last-Event-ID.
 *
 *   docker compose run --rm app php bin/aviso.php "Mensagem enquanto o servidor estava fora"
 */

require __DIR__ . '/../src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit("Use pela linha de comando.\n");
}

$texto = trim(implode(' ', array_slice($argv, 1)));
if ($texto === '') {
    fwrite(STDERR, "Uso: php bin/aviso.php \"texto do aviso\"\n");
    exit(1);
}

$id = publish(redis(), 'aviso', [
    'texto' => $texto,
    'autor' => env('ADMIN_NAME', 'Admin'),
    'em' => date(DATE_ATOM),
]);

echo "Aviso publicado com id {$id}\n";
