<?php

return [
    // Keep temporary uploads aligned with the application import limit.
    'temporary_file_upload' => [
        'rules' => ['required', 'file', 'max:20480'],
    ],
];
