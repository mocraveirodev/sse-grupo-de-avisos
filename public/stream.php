<?php

declare(strict_types=1);

/*
 * GET /stream.php  →  o endpoint SSE.
 *
 * Cada armadilha da palestra aparece aqui com um comentário [Armadilha N].
 */

require __DIR__ . '/../src/bootstrap.php';

// [Armadilha 4] Timeouts: o script precisa poder rodar por muito tempo.
set_time_limit(0);
// Queremos tratar a desconexão nós mesmos (para limpar a presença), não morrer no meio.
ignore_user_abort(true);

// [Armadilha 3] Session lock: este endpoint não usa sessão. Se o seu usar,
// leia o que precisar e chame session_write_close() ANTES do loop, senão
// todas as outras requisições do mesmo usuário ficam travadas esperando.

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-transform');
// [Armadilha 2] nginx (e outros proxies) bufferizam a resposta por padrão.
header('X-Accel-Buffering: no');

// [Armadilha 1] Output buffering: esvazia e desliga qualquer buffer do PHP.
// (o php.ini do container também tem output_buffering = 0)
while (ob_get_level() > 0) {
    ob_end_flush();
}

function send_event(?string $id, string $event, string $data): void
{
    $frame = '';
    if ($id !== null) {
        $frame .= "id: {$id}\n";
    }
    $frame .= "event: {$event}\n";
    foreach (explode("\n", $data) as $line) {
        $frame .= "data: {$line}\n";
    }
    echo $frame . "\n";
    flush();
}

// [Armadilha 7] Uma conexão Redis por aba aberta. Funciona bem para uma sala de
// palestra; para milhares de clientes, um hub como o Mercure resolve isso.
$redis = redis();

$heartbeatSeconds = max(1, (int) env('HEARTBEAT_SECONDS', '10'));
$maxSeconds = max(10, (int) env('STREAM_MAX_SECONDS', '300'));
$clientId = bin2hex(random_bytes(8));
// O painel da admin também escuta o stream, mas não conta como "pessoa no grupo".
$contaComoPresenca = ($_GET['papel'] ?? '') !== 'admin';

// Se a conexão cair, o navegador reconecta sozinho depois de 2s.
echo "retry: 2000\n\n";
flush();

// --- De onde continuar? -------------------------------------------------------
// Na reconexão o navegador manda o header Last-Event-ID com o último id recebido.
// (o ?lastEventId= existe só para facilitar testes com curl)
$lastEventId = $_SERVER['HTTP_LAST_EVENT_ID'] ?? ($_GET['lastEventId'] ?? '');
$isResume = is_string($lastEventId) && preg_match('/^\d+-\d+$/', $lastEventId) === 1;

if ($isResume) {
    // O XREAD logo abaixo devolve tudo que veio DEPOIS desse id: é o "replay".
    $lastId = $lastEventId;
} else {
    // Primeira conexão: manda os últimos avisos para quem acabou de entrar no grupo.
    $recent = array_reverse($redis->xRevRange(STREAM_KEY, '+', '-', 200) ?: [], true);
    $lastId = '0-0';
    $avisos = [];
    foreach ($recent as $id => $fields) {
        $lastId = (string) $id;
        if (($fields['type'] ?? '') === 'aviso') {
            $avisos[$id] = $fields['data'];
        }
    }
    foreach (array_slice($avisos, -20, null, true) as $id => $data) {
        send_event((string) $id, 'aviso', $data);
    }
}

// Placar atual de reações (sem id, para não mexer no Last-Event-ID).
send_event(null, 'reacoes', json_encode(reaction_totals($redis), JSON_UNESCAPED_UNICODE));

$startedAt = time();
$lastBeat = 0;

try {
    while (true) {
        // [Armadilha 5] Sem isso, o PHP continua rodando para um cliente que já foi embora.
        if (connection_aborted()) {
            break;
        }

        // Encerrar de tempos em tempos libera o worker/thread; o navegador
        // reconecta com Last-Event-ID e não perde nada.
        if (time() - $startedAt >= $maxSeconds) {
            break;
        }

        if (time() - $lastBeat >= $heartbeatSeconds) {
            // [Armadilha 5] Heartbeat: um comentário SSE (linha começando com ":").
            // Mantém proxies acordados e é o que faz o connection_aborted() perceber a queda.
            echo ": ping\n\n";
            flush();

            $now = time();
            if ($contaComoPresenca) {
                $redis->zAdd(PRESENCE_KEY, $now, $clientId);
            }
            $redis->zRemRangeByScore(PRESENCE_KEY, '-inf', (string) ($now - 3 * $heartbeatSeconds));
            send_event(null, 'presenca', json_encode(['online' => $redis->zCard(PRESENCE_KEY)]));
            $lastBeat = $now;
        }

        // [Armadilha 7] Nada de "SELECT ... + sleep(1)" aqui: o XREAD BLOCK fica parado
        // no Redis até chegar evento novo (ou até o próximo heartbeat).
        $result = $redis->xRead([STREAM_KEY => $lastId], 100, $heartbeatSeconds * 1000);

        foreach (($result[STREAM_KEY] ?? []) as $id => $fields) {
            $lastId = (string) $id;
            $type = (string) ($fields['type'] ?? 'mensagem');
            $data = (string) ($fields['data'] ?? '{}');

            if ($type === 'derrubar') {
                // Ato 3: "volta daqui a N segundos". Só vale para pedidos recentes,
                // para quem reconecta depois não ser derrubado de novo pelo replay.
                $segundos = (int) (json_decode($data, true)['segundos'] ?? 10);
                $criadoEmMs = (int) explode('-', $lastId)[0];
                if ((microtime(true) * 1000) - $criadoEmMs < $segundos * 1000) {
                    echo 'retry: ' . ($segundos * 1000) . "\n\n";
                    send_event($lastId, 'derrubar', $data);
                    break 2; // sai do foreach e do while: a conexão fecha aqui
                }
                continue;
            }

            send_event($lastId, $type, $data);
        }
    }
} finally {
    $redis->zRem(PRESENCE_KEY, $clientId);
    $redis->close();
}
