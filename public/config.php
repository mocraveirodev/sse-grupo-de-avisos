<?php

declare(strict_types=1);

/*
 * GET /config.php  →  descobre o endereço público que vai no QR code do painel.
 *
 * 1. Se o túnel do Cloudflare estiver rodando (docker compose --profile tunnel up),
 *    pergunta para ele qual endereço *.trycloudflare.com foi gerado.
 * 2. Senão, usa o PUBLIC_URL do .env, se existir.
 */

require __DIR__ . '/../src/bootstrap.php';

require_method('GET');

$tunnelUrl = null;
$metricsUrl = env('TUNNEL_METRICS_URL', 'http://tunnel:2000/quicktunnel');
$raw = @file_get_contents($metricsUrl, false, stream_context_create(['http' => ['timeout' => 1]]));
if (is_string($raw)) {
    $hostname = (string) (json_decode($raw, true)['hostname'] ?? '');
    if (preg_match('/^[a-z0-9.-]+$/i', $hostname) === 1) {
        $tunnelUrl = "https://{$hostname}/";
    }
}

$publicUrl = env('PUBLIC_URL');

json_response([
    'tunnelUrl' => $tunnelUrl,
    'publicUrl' => $publicUrl !== '' ? $publicUrl : null,
]);
