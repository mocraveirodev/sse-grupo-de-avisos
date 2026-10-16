<?php

declare(strict_types=1);

/*
 * POST /derrubar.php  →  o "Ato 3" da demo (só admin).
 *
 * Manda todo mundo desconectar e só voltar daqui a N segundos, usando o
 * próprio protocolo SSE: o servidor envia um novo "retry:" e fecha a conexão.
 * Os avisos mandados nesse intervalo chegam quando o navegador reconectar,
 * graças ao Last-Event-ID.
 */

require __DIR__ . '/../src/bootstrap.php';

require_method('POST');
$body = request_body();
require_admin($body);

$segundos = max(3, min(60, (int) ($body['segundos'] ?? 10)));
$id = publish(redis(), 'derrubar', ['segundos' => $segundos]);

json_response(['ok' => true, 'id' => $id, 'segundos' => $segundos]);
