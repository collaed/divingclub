<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\DivingClubUserProvider;
use App\Services\LicenseService;
use App\Services\MailBalancer;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
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

        // Staging mail: whitelist gets real email, everyone else → always_to
        if (config('app.staging_mode') && $fallback = config('mail.always_to')) {
            $whitelist = array_map('strtolower', config('mail.whitelist', []));
            if (empty($whitelist)) {
                Mail::alwaysTo($fallback);
            } else {
                Event::listen(MessageSending::class, function (MessageSending $event) use ($whitelist, $fallback) {
                    $to = $event->message->getTo();
                    $addresses = array_map(fn ($a) => strtolower($a->getAddress()), $to);
                    $allowed = array_filter($addresses, fn ($a) => in_array($a, $whitelist));
                    if (empty($allowed)) {
                        // No whitelisted recipients — redirect to fallback
                        $event->message->to($fallback);
                    }
                    // If any whitelisted, send to original recipients
                });
            }
        }

        // Load-balance outgoing mail across providers
        Event::listen(MessageSending::class, function () {
            $provider = MailBalancer::configureForNext();
            MailBalancer::recordSend($provider);
        });

        // @icon('🤿') — outputs emoji only when icons are enabled for current user
        Blade::directive('icon', function (string $expression) {
            return "<?php echo \App\Helpers\IconHelper::render({$expression}); ?>";
        });
    }
}
