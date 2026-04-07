<?php

return [
    'max_file_size_kb' => 10240,

    'images' => [
        'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'mimetypes' => ['image/jpeg', 'image/png', 'image/webp'],
    ],

    'documents' => [
        'extensions' => ['pdf', 'csv'],
        'mimetypes' => [
            'application/pdf',
            'text/csv',
            'text/plain',
            'application/csv',
            'application/vnd.ms-excel',
            'text/comma-separated-values',
        ],
    ],

    'rate_limits' => [
        'authenticated_uploads_per_minute' => 20,
        'authenticated_uploads_per_ip_per_minute' => 60,
        'public_uploads_per_ip_per_minute' => 10,
    ],
];
