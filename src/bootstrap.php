<?php

declare(strict_types=1);

/*
 * Configuração e funções compartilhadas pelos endpoints.
 *
 * Todo o "estado" da demo vive no Redis:
 *   - grupo:avisos   → um Redis Stream com todos os eventos (avisos, reações, reset).
 *                      Os IDs do stream ("1729100000000-0") viram o `id:` do SSE,
 *                      e é isso que faz o Last-Event-ID funcionar de graça.
 *   - grupo:reacoes  → um hash com o total de cada reação.
 *   - grupo:online   → um sorted set com quem está conectado (para o contador).
 */

const STREAM_KEY = 'grupo:avisos';
const REACTIONS_KEY = 'grupo:reacoes';
const PRESENCE_KEY = 'grupo:online';
const STREAM_MAXLEN = 1000;
const ALLOWED_REACTIONS = ['👍', '😂', '🔥', '❤️', '🤯'];

function env(string $key, string $default = ''): string
{
    $value = getenv($key);

    return ($value === false || $value === '') ? $default : $value;
}

function redis(): Redis
{
    $redis = new Redis();

    // REDIS_URL (ex.: redis://usuario:senha@host:6379, como o Render entrega)
    // tem prioridade sobre REDIS_HOST/REDIS_PORT.
    $url = env('REDIS_URL');
    if ($url !== '') {
        $parts = parse_url($url) ?: [];
        $host = ($parts['scheme'] ?? 'redis') === 'rediss' ? 'tls://' . ($parts['host'] ?? '') : ($parts['host'] ?? '');
        $redis->connect($host, (int) ($parts['port'] ?? 6379), 2.0);
        if (isset($parts['pass'])) {
            $redis->auth(isset($parts['user']) ? [urldecode($parts['user']), urldecode($parts['pass'])] : urldecode($parts['pass']));
        }
    } else {
        $redis->connect(env('REDIS_HOST', '127.0.0.1'), (int) env('REDIS_PORT', '6379'), 2.0);
    }

    // O XREAD BLOCK espera alguns segundos; o timeout de leitura precisa ser maior que isso.
    $redis->setOption(Redis::OPT_READ_TIMEOUT, 30);

    return $redis;
}

/** Publica um evento no stream e devolve o ID gerado pelo Redis. */
function publish(Redis $redis, string $type, array $data): string
{
    return $redis->xAdd(
        STREAM_KEY,
        '*',
        ['type' => $type, 'data' => json_encode($data, JSON_UNESCAPED_UNICODE)],
        STREAM_MAXLEN,
        true // MAXLEN ~ (aproximado), bem mais barato pro Redis
    );
}

function reaction_totals(Redis $redis): array
{
    $totals = [];
    $stored = $redis->hGetAll(REACTIONS_KEY) ?: [];
    foreach (ALLOWED_REACTIONS as $emoji) {
        $totals[$emoji] = (int) ($stored[$emoji] ?? 0);
    }

    return $totals;
}

function json_response(array $body, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
        header('Allow: ' . $method);
        json_response(['erro' => "Use $method neste endpoint."], 405);
    }
}

/** Lê o corpo como JSON (fetch) ou como formulário (curl -d / <form>). */
function request_body(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode((string) file_get_contents('php://input'), true);

        return is_array($decoded) ? $decoded : [];
    }

    return $_POST;
}

function require_admin(array $body): void
{
    $sent = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? (string) ($body['token'] ?? '');
    if (!hash_equals(env('ADMIN_TOKEN', 'phpeste'), $sent)) {
        json_response(['erro' => 'Token de admin inválido.'], 401);
    }
}
