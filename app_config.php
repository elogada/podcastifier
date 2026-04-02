<?php

declare(strict_types=1);

function app_config(): array
{
    static $config = [
        'piper' => [
            'runtime' => [
                'version' => '2023.11.14-2',
                'archive' => 'piper_windows_amd64.zip',
                'url' => 'https://github.com/rhasspy/piper/releases/download/2023.11.14-2/piper_windows_amd64.zip',
            ],
        ],
    ];

    return $config;
}

function app_config_get(string $path, mixed $default = null): mixed
{
    $segments = explode('.', $path);
    $value = app_config();

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}
