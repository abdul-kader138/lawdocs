<?php

return [

    // Storage disk (see config/filesystems.php) files are physically written to.
    'disk' => env('DOCUMENTS_DISK', 'documents'),

    // Per-owner storage quota shown in the File Manager's usage bar.
    'quota_mb_per_owner' => (int) env('DOCUMENTS_QUOTA_MB', 2048),

    // Largest single upload accepted, enforced server-side regardless of client behavior.
    'max_upload_mb' => (int) env('DOCUMENTS_MAX_UPLOAD_MB', 100),

];
