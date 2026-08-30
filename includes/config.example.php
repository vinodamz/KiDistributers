<?php
// Copy this file to includes/config.php and fill in the real values.
// includes/config.php is gitignored — never commit DB credentials.

return [
    'db' => [
        'host'     => 'localhost',
        'name'     => 'cpaneluser_kd',
        'user'     => 'cpaneluser_kd',
        'password' => 'CHANGE_ME',
        'charset'  => 'utf8mb4',
    ],
    'app' => [
        'name'          => 'Ki Distributers',
        'short_name'    => 'KD',
        'session_name'  => 'KI_SESSION',
        'max_pin_tries' => 5,
        'lock_seconds'  => 30,
        'timezone'      => 'Asia/Kolkata',
    ],
];
