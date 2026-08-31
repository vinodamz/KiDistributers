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
    // Super-app launcher at /apps/. Distribution stays at /. Add other businesses as extra rows.
    'hub' => [
        'name' => 'Ki',
        'businesses' => [
            [
                'id'       => 'kd',
                'name'     => 'Ki Distributers',
                'subtitle' => 'Outlets · orders · van · stock',
                'href'     => '/index.php',
                'icon'     => 'distribution',
            ],
            // [
            //     'id'       => 'school',
            //     'name'     => 'Little Graduates',
            //     'subtitle' => 'School operations',
            //     'href'     => 'https://thelittlegraduates.in/',
            //     'icon'     => 'school',
            // ],
        ],
    ],
];
