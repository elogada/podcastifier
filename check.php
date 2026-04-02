<?php

declare(strict_types=1);
require __DIR__ . '/common.php';
require __DIR__ . '/piper.php';
initialize_json_endpoint();

json_response(run_piper_system_check());
