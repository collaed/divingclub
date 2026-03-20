<?php

use App\Providers\AppServiceProvider;
use App\Providers\StagingMailServiceProvider;
use App\Providers\ThemeServiceProvider;

return [
    AppServiceProvider::class,
    ThemeServiceProvider::class,
    StagingMailServiceProvider::class,
];
