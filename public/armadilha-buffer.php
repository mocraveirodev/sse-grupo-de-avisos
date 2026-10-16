<?php

declare(strict_types=1);

/*
 * GET /armadilha-buffer.php              →  a Armadilha 1 ao vivo (quebrado)
 * GET /armadilha-buffer.php?corrigido=1  →  o mesmo código, consertado
 *
 * Rode os dois lado a lado com:
 *   curl -N http://localhost:8080/armadilha-buffer.php
 *   curl -N "http://localhost:8080/armadilha-buffer.php?corrigido=1"
 *
 * O quebrado fica mudo por 10 segundos e despeja tudo de uma vez no final.
 */

set_time_limit(30);

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

$corrigido = isset($_GET['corrigido']);

if ($corrigido) {
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
} else {
    // Simula o php.ini padrão de muita hospedagem (output_buffering = 4096).
    ob_start(null, 4096);
}

for ($i = 1; $i <= 10; $i++) {
    $hora = date('H:i:s');
    echo "event: tick\n";
    echo "data: {\"n\": {$i}, \"hora\": \"{$hora}\"}\n\n";

    if ($corrigido) {
        flush();
    }
    // No modo quebrado, o texto fica preso no buffer do PHP.

    sleep(1);
}
