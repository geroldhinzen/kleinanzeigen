<?php
return [
    'DB_HOST' => getenv('DB_HOST') ?: 'localhost',
    'DB_USER' => getenv('DB_USER') ?: 'root',
    'DB_PASS' => getenv('DB_PASS') ?: 'root',
    'DB_NAME' => getenv('DB_NAME') ?: 'kleinanzeigen',
    'DB_PORT' => getenv('DB_PORT') ?: 8889,
];
