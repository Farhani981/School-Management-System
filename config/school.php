<?php

return [
    // No school branding settings table exists yet. These settings provide its fallback.
    'name' => env('SCHOOL_NAME', env('APP_NAME', 'Laravel') === 'Laravel' ? 'School Management System' : env('APP_NAME')),
    'logo' => env('SCHOOL_LOGO'), // Local public asset URL, e.g. /storage/school/logo.png
];
