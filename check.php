<?php

declare(strict_types=1);
require __DIR__ . '/common.php';
initialize_json_endpoint();

json_response(run_system_check());
