<?php

declare(strict_types=1);

/**
 * The compte-rendu action-extraction task always reads documents from the
 * app it runs on, but the two settings below let it write the resulting
 * cards to a *different* app — needed because the documents only live on
 * production while the board is reviewed on staging.
 */
return [
    // Set on the app that sources the documents (production): the board
    // app's ingest endpoint. When empty, extracted cards are saved locally
    // instead.
    'push_url' => env('KANBAN_PUSH_URL'),
    'push_token' => env('KANBAN_PUSH_TOKEN'),

    // Set on the app that hosts the board (staging): the token this app's
    // own ingest endpoint requires from a caller. Requests without a
    // matching token are refused; if this is empty the endpoint is closed.
    'ingest_token' => env('KANBAN_INGEST_TOKEN'),
];
