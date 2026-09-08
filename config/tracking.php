<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Page-visit logging
    |--------------------------------------------------------------------------
    |
    | When enabled, the TrackActivity middleware records every authenticated
    | GET page view (path, route name, HTTP status) in the `page_visits`
    | table so bureau_master can reconstruct what a member actually did when
    | they report "it doesn't work". When disabled, only `users.last_seen_at`
    | is kept up to date. Flip this off to stop logging without a deploy.
    |
    */

    'page_visits' => (bool) env('TRACKING_PAGE_VISITS', true),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Days of `page_visits` history to keep. Older rows are deleted daily by
    | the `tracking:prune` scheduled command.
    |
    */

    'retention_days' => (int) env('TRACKING_RETENTION_DAYS', 3),

];
