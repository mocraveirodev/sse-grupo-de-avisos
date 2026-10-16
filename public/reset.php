<?php

declare(strict_types=1);

/*
 * POST /reset.php  →  limpa avisos e reações (só admin). Útil antes de subir no palco.
 */

require __DIR__ . '/../src/bootstrap.php';

require_method('POST');
$body = request_body();
require_admin($body);

$redis = redis();
$redis->del(STREAM_KEY, REACTIONS_KEY);
$id = publish($redis, 'reset', ['em' => date(DATE_ATOM)]);

json_response(['ok' => true, 'id' => $id]);
