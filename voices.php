<?php

declare(strict_types=1);
require __DIR__ . '/common.php';
require __DIR__ . '/piper.php';
initialize_json_endpoint();

json_response([
    'ok' => true,
    'voices' => get_installed_piper_voices(),
    'catalog' => get_piper_voice_catalog_with_status(),
    'default_voice_id' => default_piper_voice_id(),
]);
