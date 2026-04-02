<?php

declare(strict_types=1);
require __DIR__ . '/common.php';
initialize_json_endpoint();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$pid = is_file(pid_file()) ? (int) trim((string) file_get_contents(pid_file())) : 0;
if ($pid <= 0) {
    set_status('idle', 'Nothing is currently running.');
    json_response(['ok' => true, 'message' => 'Nothing is currently running.']);
}

@exec('taskkill /F /PID ' . $pid . ' 2>NUL');
@unlink(pid_file());
@unlink(pending_audio_file());
set_status('stopped', 'Generation stopped by user.', ['error' => null]);
json_response(['ok' => true, 'message' => 'Generation stopped.']);
