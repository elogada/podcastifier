<?php

declare(strict_types=1);
require __DIR__ . '/common.php';
initialize_json_endpoint();

$status = get_status();
$pid = is_file(pid_file()) ? (int) trim((string) file_get_contents(pid_file())) : 0;

if (($status['state'] ?? '') === 'processing' && $pid > 0 && !is_process_running($pid)) {
    if (is_file(final_audio_file())) {
        $status = array_merge($status, [
            'state' => 'done',
            'message' => 'Audio is ready.',
            'updated_at' => date('c'),
        ]);
        write_json_file(status_file(), $status);
    } else {
        $status = array_merge($status, [
            'state' => 'failed',
            'message' => 'The speech process stopped before finishing.',
            'error' => 'TTS process ended unexpectedly.',
            'updated_at' => date('c'),
        ]);
        write_json_file(status_file(), $status);
    }
}

$status['audio_exists'] = is_file(final_audio_file());
$status['audio_url'] = $status['audio_exists'] ? 'runtime/output.wav?v=' . time() : null;
$status['pid'] = $pid > 0 ? $pid : null;
json_response($status);
