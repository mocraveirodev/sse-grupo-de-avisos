<?php

declare(strict_types=1);

/*
 * GET /polling.php?depois=<id>  →  a versão "Já chegou? Já chegou?" do mesmo grupo.
 *
 * Existe só para comparar com o SSE na página /polling.html:
 * o navegador pergunta a cada N segundos, mesmo quando não tem nada novo.
 */

require __DIR__ . '/../src/bootstrap.php';

require_method('GET');

$redis = redis();
$depois = (string) ($_GET['depois'] ?? '');

if (preg_match('/^\d+-\d+$/', $depois) === 1) {
    // "(" = intervalo exclusivo: só o que veio depois desse id.
    $entries = $redis->xRange(STREAM_KEY, '(' . $depois, '+', 100) ?: [];
} else {
    $entries = array_reverse($redis->xRevRange(STREAM_KEY, '+', '-', 20) ?: [], true);
}

$itens = [];
$ultimoId = $depois !== '' ? $depois : '0-0';
foreach ($entries as $id => $fields) {
    $ultimoId = (string) $id;
    $itens[] = [
        'id' => $ultimoId,
        'tipo' => $fields['type'] ?? 'mensagem',
        'dados' => json_decode($fields['data'] ?? '{}', true),
    ];
}

json_response(['itens' => $itens, 'ultimoId' => $ultimoId]);
