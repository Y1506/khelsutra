<?php

return [
    'medical' => [
        // 'strict' (fail if unfit/unknown), 'lenient' (warn if unknown, fail if unfit), 'none'
        'gate_mode' => env('LOGISTICS_MEDICAL_GATE_MODE', 'strict'),
    ],
    'waitlist' => [
        'max_size' => env('LOGISTICS_WAITLIST_MAX_SIZE', 50),
    ],
];
