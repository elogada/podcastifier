<?php

declare(strict_types=1);

require_once __DIR__ . '/app_config.php';
require_once __DIR__ . '/zip_utils.php';

function can_download_remote(): bool
{
    return extension_loaded('curl') || filter_var((string) ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL);
}

function piper_path(string $append = ''): string
{
    return runtime_path('piper' . ($append ? DIRECTORY_SEPARATOR . $append : ''));
}

function piper_engine_dir(): string
{
    return piper_path('engine');
}

function piper_download_dir(): string
{
    return piper_path('downloads');
}

function piper_voice_dir(string $append = ''): string
{
    return piper_path('voices' . ($append ? DIRECTORY_SEPARATOR . $append : ''));
}

function ensure_piper_dirs(): void
{
    foreach ([piper_path(), piper_download_dir(), piper_voice_dir()] as $dir) {
        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create directory: ' . $dir);
        }
    }
}

function piper_runtime_manifest(): array
{
    return [
        'version' => (string) app_config_get('piper.runtime.version', '2023.11.14-2'),
        'url' => (string) app_config_get('piper.runtime.url', ''),
        'archive' => (string) app_config_get('piper.runtime.archive', 'piper_windows_amd64.zip'),
    ];
}

function piper_voice_catalog(): array
{
    return [
        'en_US-joe-medium' => [
            'id' => 'en_US-joe-medium',
            'label' => 'English (United States) - Joe',
            'description' => 'Default voice. Balanced American English voice for most use cases.',
            'size_label' => '63 MB',
            'default' => true,
            'model' => 'en_US-joe-medium.onnx',
            'config' => 'en_US-joe-medium.onnx.json',
            'model_url' => 'https://huggingface.co/rhasspy/piper-voices/resolve/main/en/en_US/joe/medium/en_US-joe-medium.onnx?download=true',
            'config_url' => 'https://huggingface.co/rhasspy/piper-voices/resolve/main/en/en_US/joe/medium/en_US-joe-medium.onnx.json?download=true',
        ],
        'en_GB-cori-medium' => [
            'id' => 'en_GB-cori-medium',
            'label' => 'English (Great Britain) - Cori',
            'description' => 'Optional British English voice with a brighter tone.',
            'size_label' => '64 MB',
            'default' => false,
            'model' => 'en_GB-cori-medium.onnx',
            'config' => 'en_GB-cori-medium.onnx.json',
            'model_url' => 'https://huggingface.co/rhasspy/piper-voices/resolve/main/en/en_GB/cori/medium/en_GB-cori-medium.onnx?download=true',
            'config_url' => 'https://huggingface.co/rhasspy/piper-voices/resolve/main/en/en_GB/cori/medium/en_GB-cori-medium.onnx.json?download=true',
        ],
        'en_GB-alan-medium' => [
            'id' => 'en_GB-alan-medium',
            'label' => 'English (Great Britain) - Alan',
            'description' => 'Optional British English voice with a steadier presentation style.',
            'size_label' => '64 MB',
            'default' => false,
            'model' => 'en_GB-alan-medium.onnx',
            'config' => 'en_GB-alan-medium.onnx.json',
            'model_url' => 'https://huggingface.co/rhasspy/piper-voices/resolve/main/en/en_GB/alan/medium/en_GB-alan-medium.onnx?download=true',
            'config_url' => 'https://huggingface.co/rhasspy/piper-voices/resolve/main/en/en_GB/alan/medium/en_GB-alan-medium.onnx.json?download=true',
        ],
    ];
}

function default_piper_voice_id(): string
{
    foreach (piper_voice_catalog() as $voiceId => $voice) {
        if (!empty($voice['default'])) {
            return $voiceId;
        }
    }

    return 'en_US-joe-medium';
}

function get_piper_voice(string $voiceId): ?array
{
    $catalog = piper_voice_catalog();
    return $catalog[$voiceId] ?? null;
}

function piper_voice_model_path(string $voiceId): string
{
    $voice = get_piper_voice($voiceId);
    if ($voice === null) {
        throw new InvalidArgumentException('Unknown Piper voice: ' . $voiceId);
    }

    return piper_voice_dir($voiceId . DIRECTORY_SEPARATOR . $voice['model']);
}

function piper_voice_config_path(string $voiceId): string
{
    $voice = get_piper_voice($voiceId);
    if ($voice === null) {
        throw new InvalidArgumentException('Unknown Piper voice: ' . $voiceId);
    }

    return piper_voice_dir($voiceId . DIRECTORY_SEPARATOR . $voice['config']);
}

function find_file_recursively(string $directory, string $filename): ?string
{
    if (!is_dir($directory)) {
        return null;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strcasecmp($file->getFilename(), $filename) === 0) {
            return $file->getPathname();
        }
    }

    return null;
}

function piper_executable_path(): ?string
{
    return find_file_recursively(piper_engine_dir(), 'piper.exe');
}

function piper_espeak_data_path(): ?string
{
    if (!is_dir(piper_engine_dir())) {
        return null;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(piper_engine_dir(), FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir() && strcasecmp($item->getFilename(), 'espeak-ng-data') === 0) {
            return $item->getPathname();
        }
    }

    return null;
}

function remove_directory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }

    @rmdir($directory);
}

function copy_directory(string $source, string $destination): void
{
    if (!is_dir($destination) && !@mkdir($destination, 0777, true) && !is_dir($destination)) {
        throw new RuntimeException('Could not create directory: ' . $destination);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relativePath = substr($item->getPathname(), strlen($source) + 1);
        $targetPath = $destination . DIRECTORY_SEPARATOR . $relativePath;

        if ($item->isDir()) {
            if (!is_dir($targetPath) && !@mkdir($targetPath, 0777, true) && !is_dir($targetPath)) {
                throw new RuntimeException('Could not create directory: ' . $targetPath);
            }
            continue;
        }

        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Could not create directory: ' . $targetDir);
        }

        if (!@copy($item->getPathname(), $targetPath)) {
            throw new RuntimeException('Could not copy file: ' . $relativePath);
        }
    }
}

function download_file(string $url, string $destination): void
{
    $directory = dirname($destination);
    if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create download directory.');
    }

    $tempPath = $destination . '.part';
    @unlink($tempPath);

    if (extension_loaded('curl')) {
        $handle = fopen($tempPath, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Could not create download file.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $handle,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FAILONERROR => true,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_USERAGENT => 'Podcastifier/1.0',
        ]);

        $ok = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        fclose($handle);

        if ($ok !== true) {
            @unlink($tempPath);
            throw new RuntimeException('Download failed' . ($status > 0 ? ' (HTTP ' . $status . ')' : '') . ($error !== '' ? ': ' . $error : '.'));
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'follow_location' => 1,
                'timeout' => 20,
                'user_agent' => 'Podcastifier/1.0',
            ],
            'https' => [
                'follow_location' => 1,
                'timeout' => 20,
                'user_agent' => 'Podcastifier/1.0',
            ],
        ]);

        $stream = @fopen($url, 'rb', false, $context);
        if (!is_resource($stream)) {
            throw new RuntimeException('Could not open remote download: ' . $url);
        }

        $handle = fopen($tempPath, 'wb');
        if ($handle === false) {
            fclose($stream);
            throw new RuntimeException('Could not create download file.');
        }

        stream_copy_to_stream($stream, $handle);
        fclose($stream);
        fclose($handle);
    }

    if (!@rename($tempPath, $destination)) {
        @unlink($tempPath);
        throw new RuntimeException('Could not finalize the downloaded file.');
    }
}

function extract_zip_archive(string $zipPath, string $destination): void
{
    remove_directory($destination);
    if (!@mkdir($destination, 0777, true) && !is_dir($destination)) {
        throw new RuntimeException('Could not create extraction directory.');
    }

    zip_extract_archive($zipPath, $destination);
}

function ensure_piper_runtime_installed(): void
{
    ensure_piper_dirs();

    if (piper_executable_path() !== null && piper_espeak_data_path() !== null) {
        return;
    }

    if (!can_download_remote()) {
        throw new RuntimeException('Remote downloads are not available in this PHP setup.');
    }

    $manifest = piper_runtime_manifest();
    $archivePath = piper_download_dir() . DIRECTORY_SEPARATOR . $manifest['archive'];
    $extractPath = piper_download_dir() . DIRECTORY_SEPARATOR . 'engine-extract';

    download_file($manifest['url'], $archivePath);
    extract_zip_archive($archivePath, $extractPath);

    $foundExe = find_file_recursively($extractPath, 'piper.exe');
    if ($foundExe === null) {
        remove_directory($extractPath);
        throw new RuntimeException('The downloaded Piper package does not contain piper.exe.');
    }

    $sourceRoot = dirname($foundExe);
    remove_directory(piper_engine_dir());
    copy_directory($sourceRoot, piper_engine_dir());
    remove_directory($extractPath);

    if (piper_executable_path() === null || piper_espeak_data_path() === null) {
        throw new RuntimeException('Piper runtime files were downloaded, but the installation is incomplete.');
    }
}

function ensure_piper_voice_installed(string $voiceId): void
{
    $voice = get_piper_voice($voiceId);
    if ($voice === null) {
        throw new InvalidArgumentException('Unknown Piper voice.');
    }

    ensure_piper_dirs();

    if (!can_download_remote()) {
        throw new RuntimeException('Remote downloads are not available in this PHP setup.');
    }

    $voiceDirectory = piper_voice_dir($voiceId);
    if (!is_dir($voiceDirectory) && !@mkdir($voiceDirectory, 0777, true) && !is_dir($voiceDirectory)) {
        throw new RuntimeException('Could not create the Piper voice directory.');
    }

    $modelPath = $voiceDirectory . DIRECTORY_SEPARATOR . $voice['model'];
    $configPath = $voiceDirectory . DIRECTORY_SEPARATOR . $voice['config'];

    if (!is_file($modelPath)) {
        download_file($voice['model_url'], $modelPath);
    }

    if (!is_file($configPath)) {
        download_file($voice['config_url'], $configPath);
    }
}

function get_installed_piper_voices(): array
{
    $installed = [];

    foreach (piper_voice_catalog() as $voiceId => $voice) {
        if (is_file(piper_voice_model_path($voiceId)) && is_file(piper_voice_config_path($voiceId))) {
            $installed[] = [
                'id' => $voiceId,
                'label' => $voice['label'],
                'description' => $voice['description'],
                'size_label' => $voice['size_label'],
                'default' => (bool) $voice['default'],
            ];
        }
    }

    return $installed;
}

function get_piper_voice_catalog_with_status(): array
{
    $installedIds = array_column(get_installed_piper_voices(), 'id');
    $catalog = [];

    foreach (piper_voice_catalog() as $voiceId => $voice) {
        $catalog[] = [
            'id' => $voiceId,
            'label' => $voice['label'],
            'description' => $voice['description'],
            'size_label' => $voice['size_label'],
            'default' => (bool) $voice['default'],
            'installed' => in_array($voiceId, $installedIds, true),
        ];
    }

    return $catalog;
}

function run_piper_system_check(): array
{
    ensure_runtime_dirs();
    ensure_piper_dirs();

    $phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');
    $runtimeWritable = is_dir(runtime_path()) && is_writable(runtime_path());
    $archiveSupport = archive_support_available();
    $downloadAvailable = can_download_remote();
    $powerShellDetected = false;

    if (shell_available()) {
        $checkCmd = ps_executable() . ' -NoProfile -ExecutionPolicy Bypass -Command "Write-Output ok"';
        $output = @shell_exec($checkCmd);
        $powerShellDetected = trim((string) $output) === 'ok';
    }

    $piperInstalled = piper_executable_path() !== null && piper_espeak_data_path() !== null;
    $voices = get_installed_piper_voices();
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
            'key' => 'download',
            'label' => 'PHP can download setup files',
            'ok' => $downloadAvailable,
            'hint' => $downloadAvailable ? 'Remote downloads are available.' : 'Enable cURL or allow_url_fopen in PHP.',
        ],
        [
            'key' => 'zip',
            'label' => 'Archive support available',
            'ok' => $archiveSupport,
            'hint' => $archiveSupport ? 'ZIP archives can be read in this PHP setup.' : 'ZIP archive support is required for Piper runtime installation and DOCX uploads.',
        ],
        [
            'key' => 'powershell',
            'label' => 'Background worker launcher available',
            'ok' => $powerShellDetected,
            'hint' => $powerShellDetected ? 'PowerShell is available for background generation.' : 'PowerShell is required to launch background generation jobs.',
        ],
        [
            'key' => 'piper_runtime',
            'label' => 'Piper runtime installed',
            'ok' => $piperInstalled,
            'hint' => $piperInstalled ? 'Piper runtime is ready.' : 'Install the default voice to download Piper automatically.',
        ],
        [
            'key' => 'piper_voice',
            'label' => 'At least one Piper voice installed',
            'ok' => $voiceDetected,
            'hint' => $voiceDetected ? implode(', ', array_column($voices, 'label')) : 'Install the default Joe voice to start generating audio.',
        ],
    ];

    $allOk = !in_array(false, array_column($items, 'ok'), true);

    return [
        'ok' => $allOk,
        'items' => $items,
        'voices' => $voices,
        'catalog' => get_piper_voice_catalog_with_status(),
        'default_voice_id' => default_piper_voice_id(),
        'downloads_ready' => $downloadAvailable && $archiveSupport,
    ];
}

function build_background_process_command(string $scriptPath, array $arguments): string
{
    $innerArgs = [
        '-NoProfile',
        '-ExecutionPolicy',
        'Bypass',
        '-File',
        '"' . str_replace('"', '""', $scriptPath) . '"',
    ];

    foreach ($arguments as $argument) {
        $innerArgs[] = '"' . str_replace('"', '""', $argument) . '"';
    }

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

function build_piper_start_command(string $voiceId): string
{
    $voice = get_piper_voice($voiceId);
    if ($voice === null) {
        throw new InvalidArgumentException('Unknown Piper voice.');
    }

    return build_background_process_command(base_path('tts/piper_worker.ps1'), [
        'synth',
        piper_executable_path() ?? '',
        piper_voice_model_path($voiceId),
        piper_voice_config_path($voiceId),
        piper_espeak_data_path() ?? '',
        input_file(),
        pending_audio_file(),
        final_audio_file(),
        status_file(),
        $voice['label'],
    ]);
}

function build_piper_sync_command(string $voiceId): string
{
    $voice = get_piper_voice($voiceId);
    if ($voice === null) {
        throw new InvalidArgumentException('Unknown Piper voice.');
    }

    $arguments = [
        ps_executable(),
        '-NoProfile',
        '-ExecutionPolicy',
        'Bypass',
        '-File',
        base_path('tts/piper_worker.ps1'),
        'synth',
        piper_executable_path() ?? '',
        piper_voice_model_path($voiceId),
        piper_voice_config_path($voiceId),
        piper_espeak_data_path() ?? '',
        input_file(),
        pending_audio_file(),
        final_audio_file(),
        status_file(),
        $voice['label'],
    ];

    $escaped = array_map(static fn (string $arg): string => escapeshellarg($arg), $arguments);
    return implode(' ', $escaped);
}
