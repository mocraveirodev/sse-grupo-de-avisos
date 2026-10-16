<?php

declare(strict_types=1);

/*
 * POST /send.php  →  manda um aviso para o grupo (só admin).
 *
 *   curl -X POST http://localhost:8080/send.php \
 *        -H 'X-Admin-Token: phpeste' -d 'texto=O café acabou ☕'
 */

require __DIR__ . '/../src/bootstrap.php';

require_method('POST');
$body = request_body();
require_admin($body);

$texto = trim((string) ($body['texto'] ?? ''));
if ($texto === '') {
    json_response(['erro' => 'Escreva um aviso antes de enviar.'], 422);
}
if (mb_strlen($texto) > 280) {
    json_response(['erro' => 'Aviso grande demais (máximo de 280 caracteres).'], 422);
}

$id = publish(redis(), 'aviso', [
    'texto' => $texto,
    'autor' => env('ADMIN_NAME', 'Admin'),
    'em' => date(DATE_ATOM),
]);

json_response(['ok' => true, 'id' => $id], 201);
