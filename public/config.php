<?php

declare(strict_types=1);

/*
 * GET /config.php  →  o endereço público que vai no QR code do painel (PUBLIC_URL do .env).
 * Também serve de health check para o Render.
 */

require __DIR__ . '/../src/bootstrap.php';

require_method('GET');

$publicUrl = env('PUBLIC_URL');

json_response(['publicUrl' => $publicUrl !== '' ? $publicUrl : null]);
