<?php

use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\StagingMailServiceProvider;
use App\Providers\ThemeServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    StagingMailServiceProvider::class,
    ThemeServiceProvider::class,
];
