<?php

declare(strict_types=1);
require __DIR__ . '/common.php';
require __DIR__ . '/piper.php';
initialize_json_endpoint();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

ensure_runtime_dirs();
$check = run_piper_system_check();
if (!$check['ok']) {
    json_response([
        'ok' => false,
        'message' => 'Setup check failed. Please complete the checklist first.',
        'check' => $check,
    ], 400);
}

$current = get_status();
$runningPid = is_file(pid_file()) ? (int) trim((string) file_get_contents(pid_file())) : 0;
if (($current['state'] ?? '') === 'processing' && $runningPid > 0 && is_process_running($runningPid)) {
    json_response(['ok' => false, 'message' => 'A generation task is already running. Please stop it first.'], 409);
}

$text = trim((string) ($_POST['text'] ?? ''));
$voice = trim((string) ($_POST['voice'] ?? ''));
$rate = (int) ($_POST['rate'] ?? 0);
$rate = max(-10, min(10, $rate));

$availableVoices = array_column(get_installed_piper_voices(), 'id');
if ($voice === '' || !in_array($voice, $availableVoices, true)) {
    json_response(['ok' => false, 'message' => 'Please choose an installed Piper voice.'], 422);
}

$docxText = '';
if (isset($_FILES['docx']) && is_array($_FILES['docx']) && ($_FILES['docx']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    if (($_FILES['docx']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        json_response(['ok' => false, 'message' => 'The DOCX upload could not be read.'], 422);
    }

    $tmpName = (string) ($_FILES['docx']['tmp_name'] ?? '');
    $originalName = strtolower((string) ($_FILES['docx']['name'] ?? ''));
    if ($tmpName === '' || !str_ends_with($originalName, '.docx')) {
        json_response(['ok' => false, 'message' => 'Only DOCX uploads are supported.'], 422);
    }

    try {
        $docxText = extract_text_from_docx($tmpName);
    } catch (Throwable $e) {
        json_response(['ok' => false, 'message' => 'DOCX extraction failed: ' . $e->getMessage()], 422);
    }
}

$finalText = normalize_input_text($docxText !== '' ? $docxText : $text);
if ($finalText === '') {
    json_response(['ok' => false, 'message' => 'Please paste text or upload a DOCX file first.'], 422);
}

clear_previous_output();
if (file_put_contents(input_file(), $finalText, LOCK_EX) === false) {
    json_response(['ok' => false, 'message' => 'Could not save the input text.'], 500);
}

$initialStatus = [
    'state' => 'processing',
    'message' => 'Generating audio...',
    'voice' => get_piper_voice($voice)['label'] ?? $voice,
    'created_at' => date('c'),
    'updated_at' => date('c'),
    'error' => null,
    'input_chars' => mb_strlen($finalText),
    'rate' => $rate,
];
write_json_file(status_file(), $initialStatus);

$command = build_piper_sync_command($voice);
@shell_exec($command);
$status = get_status();

if (!is_file(final_audio_file()) || ($status['state'] ?? '') === 'failed') {
    json_response([
        'ok' => false,
        'message' => $status['message'] ?? 'The Piper engine could not finish.',
        'error' => $status['error'] ?? null,
    ], 500);
}

@unlink(pid_file());
json_response([
    'ok' => true,
    'message' => 'Audio is ready.',
    'audio_url' => 'runtime/output.wav?v=' . time(),
    'used_text' => $finalText,
]);
