<?php

return [
    /*
    |--------------------------------------------------------------------------
    | UPS Credentials
    |--------------------------------------------------------------------------
    |
    | This option specifies the UPS credentials for your account.
    | You can put it here but I strongly recommend to put thoses settings into your
    | .env & .env.example file.
    |
    */
    'access_key' => env('UPS_ACCESS_KEY', 'kwIG85ZglfMDIqHhB7jGZHE7Ay3YPXauKU1UBfH2NoivDAGalt78Zd8JDiumrfs9'),
    'user_id'    => env('UPS_USER_ID', 'sAlCR1lFnjApkIIaZU1jLdvxB8jgTMYS3yT47oMVTfUq6YpE'),
    'password'   => env('UPS_PASSWORD', 'derricka5'),
    'sandbox'    => env('UPS_SANDBOX', true), // Set it to false when your ready to use your app in production.
];
