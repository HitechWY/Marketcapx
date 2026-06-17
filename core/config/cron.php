<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cron endpoint shared secret
    |--------------------------------------------------------------------------
    |
    | Optional token used to authenticate calls to the public GET /cron endpoint
    | (handled by App\Http\Middleware\VerifyCronToken).
    |
    | - If CRON_SECRET is set in the environment, callers MUST present it either
    |   as the "X-CRON-TOKEN" request header or a "token" query parameter.
    | - If it is empty/unset, the endpoint remains open for backward compatibility
    |   (a warning is logged on each hit). Set CRON_SECRET to enforce access.
    |
    | The value is never logged or exposed by the application.
    |
    */

    'secret' => env('CRON_SECRET'),

];
