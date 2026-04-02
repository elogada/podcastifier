<?php

declare(strict_types=1);
require __DIR__ . '/common.php';
require __DIR__ . '/piper.php';
initialize_json_endpoint();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

ensure_runtime_dirs();
ensure_piper_dirs();

$voiceId = trim((string) ($_POST['voice_id'] ?? default_piper_voice_id()));
$voice = get_piper_voice($voiceId);
if ($voice === null) {
    json_response(['ok' => false, 'message' => 'Unknown Piper voice.'], 422);
}

ensure_piper_runtime_installed();
ensure_piper_voice_installed($voiceId);

json_response([
    'ok' => true,
    'message' => $voice['label'] . ' is ready.',
    'voice' => [
        'id' => $voice['id'],
        'label' => $voice['label'],
    ],
    'check' => run_piper_system_check(),
]);
