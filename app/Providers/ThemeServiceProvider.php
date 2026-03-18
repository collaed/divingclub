<?php

namespace App\Providers;

use App\Services\ThemeService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ThemeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer('components.layout', function ($view) {
            $view->with('themeCSS', ThemeService::css());
            $view->with('theme', ThemeService::settings());
        });
    }
}
