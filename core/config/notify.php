<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notification dry-run (F18a)
    |--------------------------------------------------------------------------
    |
    | Test-only safety seam. When TRUE, the notification transports
    | (App\Notify\Email|Sms|Push) build the message and write the
    | NotificationLog row but SKIP the actual outbound send (no real
    | email/SMS/push, no network). It MUST default to false so production
    | behaviour is identical to before this flag existed; it is enabled only
    | inside tests via config(['notify.dry_run' => true]).
    |
    */

    'dry_run' => (bool) env('NOTIFY_DRY_RUN', false),

];
