<?php

namespace App\Providers;

use App\Auth\DivingClubUserProvider;
use App\Services\LicenseService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * Shares license watermark with all views so PDF/HTML output can
     * display "UNLICENSED" when the installation exceeds the free tier
     * without a valid key. This is a second check point independent of
     * the CheckLicense middleware — removing the middleware alone won't
     * remove the watermark from generated documents.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Map 'email' → 'primary_email' for password reset and credential lookups
        Auth::provider('divingclub', fn ($app, $config) => new DivingClubUserProvider($app['hash'], $config['model'])
        );

        View::composer('*', function ($view) {
            if (! $view->offsetExists('licenseWatermark')) {
                $view->with('licenseWatermark', LicenseService::watermark());
            }
        });

        // Intercept all outgoing mail in staging — redirect to a single address
        if (config('app.staging_mode') && $to = config('mail.always_to')) {
            Mail::alwaysTo($to);
        }

        // @icon('🤿') — outputs emoji only when icons are enabled for current user
        Blade::directive('icon', function (string $expression) {
            return "<?php echo \App\Helpers\IconHelper::render({$expression}); ?>";
        });
    }
}
