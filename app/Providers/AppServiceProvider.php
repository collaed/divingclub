<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\DivingClubUserProvider;
use App\Jobs\ResolveLoginGeo;
use App\Models\EmailLog;
use App\Models\LoginRecord;
use App\Services\BrevoTransport;
use App\Services\LicenseService;
use App\Services\MailBalancer;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\MicrosoftExtendSocialite;

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

        Mail::extend('brevo', fn () => new BrevoTransport((string) config('services.brevo.key')));

        // Register Microsoft Socialite provider
        Event::listen(SocialiteWasCalled::class, MicrosoftExtendSocialite::class);

        // Record every successful login (form, social, EU Login) for the
        // bureau_master login-history page. Never let a logging failure block auth.
        Event::listen(Login::class, function (Login $event): void {
            // Not a real member login: a console/tinker auth, an impersonation
            // starting (the session flag is set before auth()->login()), or the
            // admin being restored when impersonation ends.
            if ((app()->runningInConsole() && ! app()->runningUnitTests())
                || session()->has('impersonating')
                || request()->routeIs('admin.stop-impersonation')) {
                return;
            }

            try {
                $record = LoginRecord::create([
                    'user_id' => $event->user->getAuthIdentifier(),
                    'guard' => $event->guard,
                    'remember' => $event->remember,
                    'ip_address' => request()->ip(),
                    'user_agent' => mb_substr((string) request()->userAgent(), 0, 1000),
                ]);
                // Resolve the country off the queue so login isn't slowed.
                ResolveLoginGeo::dispatch($record->id)->afterCommit();
            } catch (\Throwable $e) {
                report($e);
            }
        });

        // Map 'email' → 'primary_email' for password reset and credential lookups
        Auth::provider('divingclub', fn ($app, $config) => new DivingClubUserProvider($app['hash'], $config['model'])
        );

        // Reset links go to every verified address (User::sendPasswordResetNotification),
        // which sends through anonymous notifiables, so the link can't carry a
        // single ?email= hint. The reset form asks for the address anyway and the
        // token is keyed by primary_email regardless of which address was used.
        ResetPassword::createUrlUsing(
            fn ($notifiable, string $token): string => url(route('password.reset', ['token' => $token], false))
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

        // Record notification-channel mail (password reset, email verification,
        // …) in email_log so it shows on /admin/email. Feature paths that send
        // bulk / newsletter / vote / contact mail already write their own
        // EmailLog row and carry no __laravel_notification marker, so they are
        // skipped here — otherwise every such send would be logged twice.
        // Staging has its own capture (StagingMailServiceProvider); a logging
        // failure must never break the actual send.
        if (! config('app.staging_mode')) {
            Event::listen(MessageSent::class, function (MessageSent $event): void {
                try {
                    if (! isset($event->data['__laravel_notification'])) {
                        return;
                    }

                    $message = $event->message;
                    $to = collect($message->getTo())->map(fn ($a) => $a->getAddress())->implode(', ');
                    if ($to === '') {
                        return;
                    }

                    EmailLog::create([
                        'to_email' => $to,
                        'subject' => $message->getSubject() ?: '(no subject)',
                        'body' => $message->getHtmlBody() ?: $message->getTextBody() ?: '',
                        'from_email' => collect($message->getFrom())->map(fn ($a) => $a->getAddress())->first(),
                        'from_name' => collect($message->getFrom())->map(fn ($a) => $a->getName())->first() ?: null,
                        'status' => 'sent',
                        'direction' => 'outbound',
                    ]);
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        }

        // @icon('🤿') — outputs emoji only when icons are enabled for current user
        Blade::directive('icon', function (string $expression) {
            return "<?php echo \App\Helpers\IconHelper::render({$expression}); ?>";
        });
    }
}
