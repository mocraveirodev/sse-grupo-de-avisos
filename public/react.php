<?php

declare(strict_types=1);

/*
 * POST /react.php  →  qualquer pessoa reage com um emoji.
 *
 * O ponto da demo: o cliente fala com o servidor com um POST comum (fetch),
 * e o servidor fala com todo mundo pelo SSE. Para muita coisa, isso dispensa WebSocket.
 */

require __DIR__ . '/../src/bootstrap.php';

require_method('POST');
$body = request_body();

$emoji = (string) ($body['emoji'] ?? '');
if (!in_array($emoji, ALLOWED_REACTIONS, true)) {
    json_response(['erro' => 'Reação desconhecida.', 'permitidas' => ALLOWED_REACTIONS], 422);
}

$redis = redis();
$redis->hIncrBy(REACTIONS_KEY, $emoji, 1);
$totals = reaction_totals($redis);
publish($redis, 'reacoes', $totals);

json_response(['ok' => true, 'reacoes' => $totals]);
