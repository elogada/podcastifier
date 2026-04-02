<?php

declare(strict_types=1);

require_once __DIR__ . '/zip_utils.php';

function base_path(string $append = ''): string
{
    $base = __DIR__;
    if ($append === '') {
        return $base;
    }
    return $base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $append);
}

function runtime_path(string $append = ''): string
{
    return base_path('runtime' . ($append ? DIRECTORY_SEPARATOR . $append : ''));
}

function upload_path(string $append = ''): string
{
    return base_path('uploads' . ($append ? DIRECTORY_SEPARATOR . $append : ''));
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function initialize_json_endpoint(): void
{
    if (ob_get_level() === 0) {
        ob_start();
    }

    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    set_exception_handler(static function (Throwable $e): void {
        error_log(sprintf(
            'Podcastifier JSON endpoint error: %s in %s on line %d',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        json_response([
            'ok' => false,
            'message' => 'The server hit an unexpected error. Check the Apache/PHP error log for details.',
            'error' => $e->getMessage(),
        ], 500);
    });

    register_shutdown_function(static function (): void {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($error['type'] ?? 0, $fatalTypes, true)) {
            return;
        }

        error_log(sprintf(
            'Podcastifier JSON endpoint fatal error: %s in %s on line %d',
            $error['message'] ?? 'Unknown fatal error',
            $error['file'] ?? 'unknown file',
            $error['line'] ?? 0
        ));

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            json_response([
                'ok' => false,
                'message' => 'The server hit a fatal error. Check the Apache/PHP error log for details.',
                'error' => $error['message'] ?? 'Unknown fatal error',
            ], 500);
        }
    });
}

function ensure_runtime_dirs(): void
{
    foreach ([runtime_path(), upload_path()] as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }
}

function read_json_file(string $path, array $default = []): array
{
    if (!is_file($path)) {
        return $default;
    }

    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return $default;
    }

    if (str_starts_with($raw, "\xEF\xBB\xBF")) {
        $raw = substr($raw, 3);
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $default;
}

function write_json_file(string $path, array $data): bool
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($path, $json, LOCK_EX) !== false;
}

function status_file(): string
{
    return runtime_path('status.json');
}

function pid_file(): string
{
    return runtime_path('pid.txt');
}

function input_file(): string
{
    return runtime_path('input.txt');
}

function pending_audio_file(): string
{
    return runtime_path('output.pending.wav');
}

function final_audio_file(): string
{
    return runtime_path('output.wav');
}

function default_status(): array
{
    return [
        'state' => 'idle',
        'message' => 'Ready.',
        'voice' => null,
        'created_at' => null,
        'updated_at' => null,
        'error' => null,
    ];
}

function get_status(): array
{
    return read_json_file(status_file(), default_status());
}

function set_status(string $state, string $message, array $extra = []): void
{
    $status = array_merge(default_status(), get_status(), $extra);
    if ($status['created_at'] === null) {
        $status['created_at'] = date('c');
    }
    $status['state'] = $state;
    $status['message'] = $message;
    $status['updated_at'] = date('c');
    write_json_file(status_file(), $status);
}

function clear_previous_output(): void
{
    foreach ([pending_audio_file(), final_audio_file(), pid_file()] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function ps_executable(): string
{
    return 'powershell.exe';
}

function shell_available(): bool
{
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    return function_exists('shell_exec') && !in_array('shell_exec', $disabled, true);
}

function get_installed_voices(): array
{
    if (!shell_available()) {
        return [];
    }

    $script = base_path('tts/windows_tts.ps1');
    if (!is_file($script)) {
        return [];
    }

    $cmd = ps_executable()
        . ' -NoProfile -ExecutionPolicy Bypass -File '
        . escapeshellarg($script)
        . ' list';

    $output = @shell_exec($cmd);
    if (!is_string($output) || trim($output) === '') {
        return [];
    }

    $voices = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $output) ?: [])));
    return array_unique($voices);
}

function run_system_check(): array
{
    ensure_runtime_dirs();

    $phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');
    $runtimeWritable = is_dir(runtime_path()) && is_writable(runtime_path());
    $zipAvailable = class_exists('ZipArchive');

    $powerShellDetected = false;
    if (shell_available()) {
        $checkCmd = ps_executable() . ' -NoProfile -ExecutionPolicy Bypass -Command "Write-Output ok"';
        $output = @shell_exec($checkCmd);
        $powerShellDetected = trim((string) $output) === 'ok';
    }

    $voices = $powerShellDetected ? get_installed_voices() : [];
    $voiceDetected = count($voices) > 0;

    $items = [
        [
            'key' => 'php',
            'label' => 'PHP 8.2+ detected',
            'ok' => $phpOk,
            'hint' => $phpOk ? PHP_VERSION : 'PHP 8.2 or higher is required.',
        ],
        [
            'key' => 'runtime',
            'label' => 'Runtime folder writable',
            'ok' => $runtimeWritable,
            'hint' => $runtimeWritable ? 'runtime/ is writable.' : 'Make sure runtime/ exists and is writable.',
        ],
        [
            'key' => 'powershell',
            'label' => 'PowerShell detected',
            'ok' => $powerShellDetected,
            'hint' => $powerShellDetected ? 'PowerShell is available.' : 'PowerShell is required for local speech synthesis.',
        ],
        [
            'key' => 'voices',
            'label' => 'At least one local voice detected',
            'ok' => $voiceDetected,
            'hint' => $voiceDetected ? implode(', ', array_slice($voices, 0, 6)) : 'Install or enable at least one Windows speech voice.',
        ],
        [
            'key' => 'zip',
            'label' => 'DOCX support available',
            'ok' => $zipAvailable,
            'hint' => $zipAvailable ? 'ZIP extension is available.' : 'Enable the ZIP extension in PHP for DOCX uploads.',
        ],
    ];

    $allOk = !in_array(false, array_column($items, 'ok'), true);

    return [
        'ok' => $allOk,
        'items' => $items,
        'voices' => $voices,
    ];
}

function extract_text_from_docx(string $docxPath): string
{
    $xml = zip_get_entry_contents($docxPath, 'word/document.xml');
    if (!is_string($xml) || $xml === '') {
        throw new RuntimeException('No readable document text was found in the DOCX file.');
    }

    $xml = str_replace(['</w:p>', '</w:tr>', '</w:tbl>'], "\n", $xml);
    $xml = str_replace(['</w:tc>'], "\t", $xml);
    $xml = preg_replace('/<w:tab[^>]*\/>/', "\t", $xml);
    $xml = preg_replace('/<w:br[^>]*\/>/', "\n", $xml);

    $text = strip_tags($xml);
    $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $text = preg_replace('/\r\n|\r/', "\n", (string) $text);
    $text = preg_replace("/\n{3,}/", "\n\n", (string) $text);
    $text = preg_replace("/[\t ]+/", ' ', (string) $text);

    return trim((string) $text);
}

function normalize_input_text(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    return trim((string) $text);
}

function build_start_process_command(string $scriptPath, string $inputPath, string $pendingPath, string $finalPath, string $statusPath, string $voice, int $rate = 0): string
{
    $innerArgs = [
        '-NoProfile',
        '-ExecutionPolicy',
        'Bypass',
        '-File', '"' . str_replace('"', '""', $scriptPath) . '"',
        'synth',
        '-InputPath', '"' . str_replace('"', '""', $inputPath) . '"',
        '-PendingPath', '"' . str_replace('"', '""', $pendingPath) . '"',
        '-OutputPath', '"' . str_replace('"', '""', $finalPath) . '"',
        '-StatusFilePath', '"' . str_replace('"', '""', $statusPath) . '"',
        '--voice', '"' . str_replace('"', '""', $voice) . '"',
        '--rate', (string) $rate,
    ];

    $innerArgumentList = implode(' ', $innerArgs);
    $escapedArgumentList = str_replace("'", "''", $innerArgumentList);
    $escapedExe = str_replace("'", "''", ps_executable());

    return ps_executable()
        . ' -NoProfile -ExecutionPolicy Bypass -Command "'
        . '$p = Start-Process -FilePath '
        . "'"
        . $escapedExe
        . "'"
        . ' -ArgumentList '
        . "'"
        . $escapedArgumentList
        . "'"
        . ' -WindowStyle Hidden -PassThru; Write-Output $($p.Id)"';
}

function is_process_running(int $pid): bool
{
    if ($pid <= 0 || !shell_available()) {
        return false;
    }

    $cmd = 'tasklist /FI "PID eq ' . $pid . '" /NH';
    $output = @shell_exec($cmd);
    if (!is_string($output)) {
        return false;
    }

    return stripos($output, (string) $pid) !== false && stripos($output, 'No tasks are running') === false;
}
