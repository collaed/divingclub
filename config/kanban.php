<?php

declare(strict_types=1);

/**
 * The compte-rendu action-extraction task always reads documents from the
 * app it runs on. The two settings below optionally let it write the
 * resulting cards to a *different* app's board instead of its own — for
 * whenever the documents and the board it's reviewed on need to live in
 * different places. Both apps currently keep their own board locally
 * (neither env var is set); this is here for if that ever needs to change.
 */
return [
    // Set on the app that should forward its extracted cards elsewhere: the
    // other app's ingest endpoint. When empty (the current setup), extracted
    // cards are saved locally instead.
    'push_url' => env('KANBAN_PUSH_URL'),
    'push_token' => env('KANBAN_PUSH_TOKEN'),

    // Set on the app that should accept cards pushed from elsewhere: the
    // token its own ingest endpoint requires from a caller. Requests without
    // a matching token are refused; if this is empty the endpoint is closed.
    'ingest_token' => env('KANBAN_INGEST_TOKEN'),
];
