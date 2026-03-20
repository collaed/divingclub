<?php

use App\Http\Controllers\Admin\AnnualReportController;
use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DiveGroupRuleController;
use App\Http\Controllers\Admin\DiveSiteController;
use App\Http\Controllers\Admin\EmailController;
use App\Http\Controllers\Admin\EquipmentController;
use App\Http\Controllers\Admin\GuardianController;
use App\Http\Controllers\Admin\GuideController;
use App\Http\Controllers\Admin\LibraryController;
use App\Http\Controllers\Admin\LinkController;
use App\Http\Controllers\Admin\MedicalExportController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\PartnershipController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\SeasonController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ThumbnailController;
use App\Http\Controllers\Admin\TrialRequestController;
use App\Http\Controllers\Admin\VoteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\BuddyController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\ClassifiedController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DiveDataController;
use App\Http\Controllers\DiveGroupController;
use App\Http\Controllers\DocumentBrowserController;
use App\Http\Controllers\DuesCalculatorController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\GdprController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HomepageLayoutController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\InstructorAvailabilityController;
use App\Http\Controllers\MembersDirectoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\StagingMailController;
use App\Http\Controllers\TrialController;
use App\Http\Controllers\VotePublicController;
use App\Http\Middleware\CheckLicense;
use App\Jobs\SendMedicalReminders;
use App\Jobs\WeeklyBackup;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;

// Locale switch
Route::get('/locale/{locale}', function (string $locale) {
    if (in_array($locale, config('app.available_locales', ['en', 'fr', 'de', 'lb', 'pt', 'it', 'nl', 'es', 'pl', 'hu', 'ro', 'sk']))) {
        session(['locale' => $locale]);
        if (auth()->check()) {
            auth()->user()->update(['preferred_locale' => $locale]);
            auth()->user()->detail?->update(['preferred_language' => $locale]);
        }
    }

    return back();
})->name('locale.switch');

// Install wizard (only accessible when DB is empty)
Route::get('/install', [InstallController::class, 'index'])->name('install.index');
Route::post('/install', [InstallController::class, 'run'])->name('install.run');

// Public
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/article/{slug}', [HomeController::class, 'showArticle'])->name('article.show');
Route::get('/trial', [TrialController::class, 'show'])->name('trial.show');
Route::post('/trial', [TrialController::class, 'store'])->name('trial.store');
Route::get('/dues', [DuesCalculatorController::class, 'show'])->name('dues.show');
Route::post('/dues', [DuesCalculatorController::class, 'calculate'])->name('dues.calculate');
Route::get('/cotisation', fn () => view('cotisation', ['cfg' => config('cotisation')]))->name('cotisation');
Route::get('/qr/sepa-public', [QrCodeController::class, 'sepaPublic'])->name('qr.sepa.public');
Route::get('/qr/payment', [QrCodeController::class, 'signedPaymentQr'])->name('qr.payment.signed');
Route::get('/pay/verify', [QrCodeController::class, 'verifyPayment'])->name('payment.verify');
Route::get('/calendar.ics', [CalendarFeedController::class, 'ical'])->name('calendar.ics');
Route::get('/contact', fn () => view('contact'))->name('contact');

// Guest auth
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->middleware(CheckLicense::class)->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware(CheckLicense::class);
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    // Password reset
    Route::get('/forgot-password', fn () => view('auth.forgot-password'))->name('password.request');
    Route::post('/forgot-password', function (Request $request) {
        $request->validate(['email' => 'required|email']);
        Password::sendResetLink(['email' => $request->email]);

        return back()->with('success', __('Reset link sent if the email exists.'));
    })->name('password.email');
    Route::get('/reset-password/{token}', fn ($token) => view('auth.reset-password', ['token' => $token]))->name('password.reset');
    Route::post('/reset-password', function (Request $request) {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
        ]);
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => $password])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', __('Password reset!'))
            : back()->withErrors(['email' => __($status)]);
    })->name('password.update');
});

// OAuth
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('auth.social.redirect');
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('auth.social.callback');
Route::post('/auth/social/confirm-link', [SocialAuthController::class, 'confirmLink'])->middleware('auth')->name('auth.social.confirm-link');
Route::post('/auth/social/dismiss-link', [SocialAuthController::class, 'dismissLink'])->middleware('auth')->name('auth.social.dismiss-link');

// Email verification
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', fn () => view('auth.verify-email'))->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();
        // Also mark user_emails as verified
        UserEmail::where('user_id', $request->user()->id)
            ->where('email', $request->user()->primary_email)
            ->update(['is_verified' => true]);

        return redirect()->route('profile.show')->with('success', __('Email verified!'));
    })->middleware('signed')->name('verification.verify');
    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();

        return back()->with('success', __('Verification link sent!'));
    })->middleware('throttle:6,1')->name('verification.send');
});

// Logout
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Password reset request (works for both guests and authenticated users)
Route::post('/request-password-reset', function (Request $request) {
    $request->validate(['email' => 'required|email']);
    Password::sendResetLink(['email' => $request->email]);

    return back()->with('success', __('Reset link sent if the email exists.'));
})->name('password.request.send');

// Authenticated + verified routes
Route::middleware(['auth', 'verified.email'])->group(function () {
    // Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile/info', [ProfileController::class, 'updateInfo'])->name('profile.update.info');
    Route::post('/profile/private', [ProfileController::class, 'updatePrivate'])->name('profile.update.private');
    Route::post('/profile/diving', [ProfileController::class, 'updateDiving'])->name('profile.update.diving');
    Route::post('/profile/federation-key/{licence}', [ProfileController::class, 'updateFederationKey'])->name('profile.update.federation-key');
    Route::post('/profile/language', [ProfileController::class, 'updateLanguage'])->name('profile.update.language');
    Route::post('/profile/document', [ProfileController::class, 'uploadDocument'])->name('profile.document.upload');
    Route::get('/profile/document/{document}', [ProfileController::class, 'downloadDocument'])->name('profile.document.download');
    Route::post('/profile/document/{document}/verify', [ProfileController::class, 'verifyCertificate'])->name('profile.document.verify');

    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('profile.avatar.upload');
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');

    // Certification levels
    Route::post('/profile/cert', [ProfileController::class, 'addCertification'])->name('profile.cert.add');
    Route::put('/profile/cert/{certLevel}', [ProfileController::class, 'updateCertification'])->name('profile.cert.update');
    Route::post('/profile/cert/{certLevel}/primary', [ProfileController::class, 'setPrimaryCert'])->name('profile.cert.primary');
    Route::delete('/profile/cert/{certLevel}', [ProfileController::class, 'removeCertification'])->name('profile.cert.remove');

    // Members directory (visible to all authenticated users)
    Route::get('/members', [MembersDirectoryController::class, 'directory'])->name('members.directory');
    Route::get('/members/trombinoscope', [MembersDirectoryController::class, 'trombinoscope'])->name('members.trombinoscope');

    // Document browser (role-based visibility, upload for instructors/bureau)
    Route::get('/gallery', [DocumentBrowserController::class, 'gallery'])->name('gallery');
    Route::get('/documents', [DocumentBrowserController::class, 'index'])->name('documents.index');
    Route::get('/documents/{file}/download', [DocumentBrowserController::class, 'download'])->name('documents.download');
    Route::get('/documents/{file}/thumb', [DocumentBrowserController::class, 'thumb'])->name('documents.thumb');
    Route::post('/documents/upload', [DocumentBrowserController::class, 'upload'])->name('documents.upload');
    Route::post('/documents/folder', [DocumentBrowserController::class, 'createFolder'])->name('documents.create-folder');
    Route::put('/documents/{file}', [DocumentBrowserController::class, 'updateFile'])->name('documents.update');
    Route::delete('/documents/{file}', [DocumentBrowserController::class, 'destroy'])->name('documents.destroy');

    // GDPR
    Route::get('/privacy', [GdprController::class, 'consents'])->name('gdpr.consents');
    Route::post('/privacy/consent', [GdprController::class, 'updateConsent'])->name('gdpr.consent');
    Route::get('/privacy/export', [GdprController::class, 'exportData'])->name('gdpr.export');
    Route::get('/privacy/erasure', [GdprController::class, 'requestErasure'])->name('gdpr.erasure');
    Route::post('/privacy/erasure', [GdprController::class, 'confirmErasure'])->name('gdpr.erasure.confirm');

    // QR Codes
    Route::get('/qr/vcard', [QrCodeController::class, 'vcard'])->name('qr.vcard');
    Route::get('/qr/sepa/{payment}', [QrCodeController::class, 'sepa'])->name('qr.sepa');
    Route::get('/qr/federation/{licence}', [QrCodeController::class, 'federation'])->name('qr.federation');

    // Email management
    Route::post('/profile/email', [ProfileController::class, 'addEmail'])->name('profile.email.add');
    Route::post('/profile/email/{email}/primary', [ProfileController::class, 'setPrimaryEmail'])->name('profile.email.primary');
    Route::delete('/profile/email/{email}', [ProfileController::class, 'deleteEmail'])->name('profile.email.delete');

    // Events (calendar visible to all authenticated users)
    Route::get('/events', [EventController::class, 'index'])->name('events.index');

    // Classifieds (any member can post)
    Route::get('/classifieds', [ClassifiedController::class, 'index'])->name('classifieds.index');

    // Looking for Buddies
    Route::get('/buddies', [BuddyController::class, 'index'])->name('buddies.index');
    Route::post('/buddies', [BuddyController::class, 'store'])->name('buddies.store');
    Route::post('/buddies/{buddyRequest}/respond', [BuddyController::class, 'respond'])->name('buddies.respond');
    Route::post('/buddies/{buddyRequest}/close', [BuddyController::class, 'close'])->name('buddies.close');

    // Dive data import/export (UDDF + DAN DL7)
    Route::post('/dive-data/import-uddf', [DiveDataController::class, 'importUddf'])->name('dive-data.import-uddf');
    Route::get('/dive-data/export-uddf', [DiveDataController::class, 'exportUddf'])->name('dive-data.export-uddf');

    // Instructor Availability (bureau & instructors only)
    Route::middleware('role:bureau_master,bureau_finance,bureau_technical,instructor')->group(function () {
        Route::get('/availability', [InstructorAvailabilityController::class, 'index'])->name('availability.index');
        Route::post('/availability/toggle', [InstructorAvailabilityController::class, 'toggle'])->name('availability.toggle');
    });
    Route::get('/classifieds/create', [ClassifiedController::class, 'create'])->name('classifieds.create');
    Route::post('/classifieds', [ClassifiedController::class, 'store'])->name('classifieds.store');
    Route::get('/classifieds/{article}/edit', [ClassifiedController::class, 'edit'])->name('classifieds.edit');
    Route::put('/classifieds/{article}', [ClassifiedController::class, 'update'])->name('classifieds.update');
    Route::post('/classifieds/{article}/extend', [ClassifiedController::class, 'extend'])->name('classifieds.extend');
    Route::delete('/classifieds/{article}', [ClassifiedController::class, 'destroy'])->name('classifieds.destroy');

    // Article comments
    Route::post('/articles/{article}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::post('/events/{event}/register', [EventController::class, 'register'])->name('events.register');
    Route::post('/events/{event}/cancel-registration', [EventController::class, 'cancelRegistration'])->name('events.cancel-registration');
    Route::post('/events/{event}/cancel', [EventController::class, 'cancel'])->name('events.cancel');
    Route::post('/events/{event}/photos', [EventController::class, 'uploadPhoto'])->name('events.photo.upload');
    Route::delete('/events/{event}/photos/{photo}', [EventController::class, 'deletePhoto'])->name('events.photo.delete');

    // Dive groups (palanquées)
    Route::get('/events/{event}/dive-groups', [DiveGroupController::class, 'index'])->name('events.dive-groups');
    Route::post('/events/{event}/dive-groups', [DiveGroupController::class, 'store'])->name('events.dive-groups.store');
    Route::post('/dive-groups/{group}/members', [DiveGroupController::class, 'addMember'])->name('dive-groups.add-member');
    Route::delete('/dive-group-members/{member}', [DiveGroupController::class, 'removeMember'])->name('dive-groups.remove-member');
    Route::delete('/dive-groups/{group}', [DiveGroupController::class, 'destroy'])->name('dive-groups.destroy');
    Route::get('/events/{event}/dive-groups/validate', [DiveGroupController::class, 'validate_groups'])->name('events.dive-groups.validate');
    Route::get('/events/{event}/dive-groups/propose', [DiveGroupController::class, 'propose'])->name('events.dive-groups.propose');
    Route::post('/events/{event}/dive-groups/apply-proposal', [DiveGroupController::class, 'applyProposal'])->name('events.dive-groups.apply-proposal');
    Route::get('/events/{event}/dive-groups/print', [DiveGroupController::class, 'printFiche'])->name('events.dive-groups.print');

    // Stop impersonation (must be outside bureau_master group — user is impersonated)
    Route::get('/admin/stop-impersonation', [MemberController::class, 'stopImpersonation'])->name('admin.stop-impersonation');

    // Admin routes (Bureau Master)
    Route::middleware('role:bureau_master')->prefix('admin')->name('admin.')->group(function () {
        Route::post('/homepage-layout', [HomepageLayoutController::class, 'saveLayout'])->name('homepage-layout.save');
        Route::get('/export-dan', [DiveDataController::class, 'exportDan'])->name('export-dan');
        Route::get('/members', [MemberController::class, 'index'])->name('members.index');
        Route::get('/members/{user}/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::post('/members/{user}/info', [ProfileController::class, 'updateInfo'])->name('profile.update.info');
        Route::post('/members/{user}/private', [ProfileController::class, 'updatePrivate'])->name('profile.update.private');
        Route::post('/members/{user}/impersonate', [MemberController::class, 'impersonate'])->name('impersonate');
        Route::post('/members/{user}/send-reset', function (User $user) {
            Password::sendResetLink(['email' => $user->primary_email]);

            return back()->with('success', __('Password reset link sent to :email', ['email' => $user->primary_email]));
        })->name('send-reset');

        Route::resource('articles', ArticleController::class)->except('show');
        Route::post('articles/{article}/translate', [ArticleController::class, 'translate'])->name('articles.translate');
        Route::resource('links', LinkController::class)->only(['index', 'store', 'destroy']);

        // Document Library
        Route::get('/library', [LibraryController::class, 'index'])->name('library.index');
        Route::post('/library/upload', [LibraryController::class, 'upload'])->name('library.upload');
        Route::put('/library/{file}', [LibraryController::class, 'update'])->name('library.update');
        Route::delete('/library/{file}', [LibraryController::class, 'destroy'])->name('library.destroy');
        Route::get('/library/{file}/download', [LibraryController::class, 'download'])->name('library.download');
        Route::get('/library/{file}/thumb', [ThumbnailController::class, 'show'])->name('library.thumb');
        Route::post('/library/folder', [LibraryController::class, 'createFolder'])->name('library.create-folder');
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/audit-logs/export', [AuditLogController::class, 'export'])->name('audit-logs.export');
        Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
        Route::post('/audit-logs/purge', [AuditLogController::class, 'purge'])->name('audit-logs.purge');
        Route::post('/audit-logs/retention', [AuditLogController::class, 'updateRetention'])->name('audit-logs.retention');

        // Backups
        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups', [BackupController::class, 'create'])->name('backups.create');
        Route::get('/backups/{filename}', [BackupController::class, 'show'])->name('backups.show')->where('filename', '.*\.tar\.gz');
        Route::get('/backups/{filename}/download', [BackupController::class, 'download'])->name('backups.download')->where('filename', '.*\.tar\.gz');
        Route::delete('/backups/{filename}', [BackupController::class, 'destroy'])->name('backups.destroy')->where('filename', '.*\.tar\.gz');

        // Trial requests
        Route::get('/trial-requests', [TrialRequestController::class, 'index'])->name('trial-requests.index');
        Route::put('/trial-requests/{trialRequest}', [TrialRequestController::class, 'update'])->name('trial-requests.update');

        // Guardians & Parental Consent
        Route::get('/guardians', [GuardianController::class, 'index'])->name('guardians.index');
        Route::post('/guardians/link', [GuardianController::class, 'linkGuardian'])->name('guardians.link');
        Route::delete('/guardians/{link}', [GuardianController::class, 'unlinkGuardian'])->name('guardians.unlink');
        Route::post('/guardians/consent', [GuardianController::class, 'storeConsent'])->name('guardians.consent');
        Route::delete('/guardians/consent/{consent}', [GuardianController::class, 'revokeConsent'])->name('guardians.consent.revoke');
        Route::get('/guardians/consent/{consent}/download', [GuardianController::class, 'downloadConsent'])->name('guardians.consent.download');

        // Dive Sites
        Route::get('/dive-sites', [DiveSiteController::class, 'index'])->name('dive-sites.index');
        Route::get('/dive-sites/create', [DiveSiteController::class, 'create'])->name('dive-sites.create');
        Route::post('/dive-sites', [DiveSiteController::class, 'store'])->name('dive-sites.store');
        Route::get('/dive-sites/{diveSite}/edit', [DiveSiteController::class, 'edit'])->name('dive-sites.edit');
        Route::put('/dive-sites/{diveSite}', [DiveSiteController::class, 'update'])->name('dive-sites.update');
        Route::delete('/dive-sites/{diveSite}', [DiveSiteController::class, 'destroy'])->name('dive-sites.destroy');

        // Dive Group Rules
        Route::get('/dive-group-rules', [DiveGroupRuleController::class, 'index'])->name('dive-group-rules.index');
        Route::post('/dive-group-rules', [DiveGroupRuleController::class, 'store'])->name('dive-group-rules.store');
        Route::put('/dive-group-rules/{rule}', [DiveGroupRuleController::class, 'update'])->name('dive-group-rules.update');
        Route::delete('/dive-group-rules/{rule}', [DiveGroupRuleController::class, 'destroy'])->name('dive-group-rules.destroy');

        // Annual Report
        Route::get('/annual-report', [AnnualReportController::class, 'show'])->name('annual-report');

        // Medical exports
        Route::get('/medical-export', [MedicalExportController::class, 'exportList'])->name('medical-export');
        Route::get('/medical-certificates', [MedicalExportController::class, 'downloadCertificates'])->name('medical-certificates');

        // Seasons
        Route::get('/seasons', [SeasonController::class, 'index'])->name('seasons.index');
        Route::get('/seasons/create', [SeasonController::class, 'create'])->name('seasons.create');
        Route::post('/seasons', [SeasonController::class, 'store'])->name('seasons.store');
        Route::get('/seasons/{season}', [SeasonController::class, 'show'])->name('seasons.show');
        Route::post('/seasons/{season}/activate', [SeasonController::class, 'activate'])->name('seasons.activate');
        Route::post('/seasons/{season}/holidays', [SeasonController::class, 'storeHoliday'])->name('seasons.holiday.store');
        Route::delete('/seasons/holidays/{holiday}', [SeasonController::class, 'destroyHoliday'])->name('seasons.holiday.destroy');
        Route::post('/seasons/{season}/patterns', [SeasonController::class, 'storePattern'])->name('seasons.pattern.store');
        Route::delete('/seasons/patterns/{pattern}', [SeasonController::class, 'destroyPattern'])->name('seasons.pattern.destroy');
        Route::get('/seasons/{season}/preview', [SeasonController::class, 'previewGeneration'])->name('seasons.preview');
        Route::post('/seasons/{season}/generate', [SeasonController::class, 'generateEvents'])->name('seasons.generate');

        // Settings
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings/federation', [SettingsController::class, 'storeFederation'])->name('settings.federation.store');
        Route::put('/settings/federation/{federation}', [SettingsController::class, 'updateFederation'])->name('settings.federation.update');
        Route::delete('/settings/federation/{federation}', [SettingsController::class, 'destroyFederation'])->name('settings.federation.destroy');
        Route::post('/settings/status', [SettingsController::class, 'storeStatus'])->name('settings.status.store');
        Route::put('/settings/status/{status}', [SettingsController::class, 'updateStatus'])->name('settings.status.update');
        Route::post('/settings/medical-rule', [SettingsController::class, 'storeMedicalRule'])->name('settings.medical-rule.store');
        Route::put('/settings/medical-rule/{rule}', [SettingsController::class, 'updateMedicalRule'])->name('settings.medical-rule.update');
        Route::delete('/settings/medical-rule/{rule}', [SettingsController::class, 'destroyMedicalRule'])->name('settings.medical-rule.destroy');
        Route::post('/settings/maintenance-rule', [SettingsController::class, 'storeMaintenanceRule'])->name('settings.maintenance-rule.store');
        Route::put('/settings/maintenance-rule/{rule}', [SettingsController::class, 'updateMaintenanceRule'])->name('settings.maintenance-rule.update');
        Route::delete('/settings/maintenance-rule/{rule}', [SettingsController::class, 'destroyMaintenanceRule'])->name('settings.maintenance-rule.destroy');
        Route::post('/settings/theme', [SettingsController::class, 'updateTheme'])->name('settings.theme.update');
        Route::post('/settings/theme/preset', [SettingsController::class, 'applyPreset'])->name('settings.theme.preset');
        Route::post('/settings/theme/logo', [SettingsController::class, 'uploadLogo'])->name('settings.theme.logo');
        Route::post('/settings/membership-fee', [SettingsController::class, 'storeMembershipFee'])->name('settings.membership-fee.store');
        Route::delete('/settings/membership-fee/{fee}', [SettingsController::class, 'destroyMembershipFee'])->name('settings.membership-fee.destroy');

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
        Route::get('/dashboard/export', [DashboardController::class, 'exportCsv'])->name('dashboard.export');

        // Admin Guide
        Route::get('/guide', [GuideController::class, 'index'])->name('guide.index');
        Route::get('/guide/{section}', [GuideController::class, 'show'])->name('guide.show');

        // Payments
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/components', [PaymentController::class, 'components'])->name('payments.components');
        Route::post('/payments/components', [PaymentController::class, 'storeComponent'])->name('payments.component.store');
        Route::delete('/payments/components/{component}', [PaymentController::class, 'destroyComponent'])->name('payments.component.destroy');
        Route::post('/payments/{user}/calculate', [PaymentController::class, 'calculateFee'])->name('payments.calculate');
        Route::post('/payments/{user}/generate', [PaymentController::class, 'generateFee'])->name('payments.generate');
        Route::get('/payments/reconciliation', [PaymentController::class, 'reconciliation'])->name('payments.reconciliation');
        Route::post('/payments/import-statement', [PaymentController::class, 'importStatement'])->name('payments.import-statement');
        Route::post('/payments/suggest-matches', [PaymentController::class, 'suggestMatches'])->name('payments.suggest-matches');
        Route::post('/payments/confirm/{transaction}', [PaymentController::class, 'confirmMatch'])->name('payments.confirm-match');
        Route::post('/payments/ignore/{transaction}', [PaymentController::class, 'ignoreTransaction'])->name('payments.ignore');

        // Equipment
        Route::get('/equipment', [EquipmentController::class, 'index'])->name('equipment.index');
        Route::get('/equipment/create', [EquipmentController::class, 'create'])->name('equipment.create');
        Route::post('/equipment', [EquipmentController::class, 'store'])->name('equipment.store');
        Route::get('/equipment/{equipment}', [EquipmentController::class, 'show'])->name('equipment.show');
        Route::put('/equipment/{equipment}', [EquipmentController::class, 'update'])->name('equipment.update');
        Route::post('/equipment/{equipment}/loan', [EquipmentController::class, 'loan'])->name('equipment.loan');
        Route::post('/equipment/return/{loan}', [EquipmentController::class, 'returnLoan'])->name('equipment.return');
        Route::post('/equipment/maintenance/{maintenance}/complete', [EquipmentController::class, 'completeMaintenance'])->name('equipment.maintenance.complete');

        // Email
        Route::get('/email', [EmailController::class, 'index'])->name('email.index');
        Route::post('/email/template', [EmailController::class, 'storeTemplate'])->name('email.template.store');
        Route::put('/email/template/{template}', [EmailController::class, 'updateTemplate'])->name('email.template.update');
        Route::delete('/email/template/{template}', [EmailController::class, 'destroyTemplate'])->name('email.template.destroy');
        Route::post('/email/preview', [EmailController::class, 'preview'])->name('email.preview');
        Route::post('/email/send', [EmailController::class, 'send'])->name('email.send');

        // Votes
        Route::get('/votes', [VoteController::class, 'index'])->name('votes.index');
        Route::get('/votes/create', [VoteController::class, 'create'])->name('votes.create');
        Route::post('/votes', [VoteController::class, 'store'])->name('votes.store');
        Route::get('/votes/{vote}', [VoteController::class, 'show'])->name('votes.show');
        Route::post('/votes/{vote}/tokens', [VoteController::class, 'generateTokens'])->name('votes.generate-tokens');
        Route::post('/votes/{vote}/open', [VoteController::class, 'open'])->name('votes.open');
        Route::post('/votes/{vote}/close', [VoteController::class, 'close'])->name('votes.close');
        Route::post('/votes/{vote}/cancel', [VoteController::class, 'cancel'])->name('votes.cancel');

        // Club Partnerships (inter-club federation)
        Route::get('/partnerships', [PartnershipController::class, 'index'])->name('partnerships.index');
        Route::get('/partnerships/create', [PartnershipController::class, 'create'])->name('partnerships.create');
        Route::post('/partnerships', [PartnershipController::class, 'store'])->name('partnerships.store');
        Route::delete('/partnerships/{partnership}', [PartnershipController::class, 'destroy'])->name('partnerships.destroy');
        Route::get('/partnerships/{partnership}/remote-events', [PartnershipController::class, 'remoteEvents'])->name('partnerships.remote-events');
        Route::get('/partnerships/registrations', [PartnershipController::class, 'registrations'])->name('partnerships.registrations');
        Route::post('/partnerships/registrations/{registration}/approve', [PartnershipController::class, 'approveRegistration'])->name('partnerships.registrations.approve');
        Route::post('/partnerships/registrations/{registration}/reject', [PartnershipController::class, 'rejectRegistration'])->name('partnerships.registrations.reject');
    });
});

// Offline page for PWA
Route::get('/offline', fn () => view('offline'))->name('offline');

// Web-based cron trigger for shared hosting (use with cron-job.org)
// Staging mail viewer (only active when STAGING_MODE=true)
Route::prefix('staging-mail')->group(function () {
    Route::get('/', [StagingMailController::class, 'index'])->name('staging.mail.index');
    Route::get('/{mail}', [StagingMailController::class, 'show'])->name('staging.mail.show');
    Route::get('/{mail}/raw', [StagingMailController::class, 'raw'])->name('staging.mail.raw');
    Route::delete('/', [StagingMailController::class, 'clear'])->name('staging.mail.clear');
});

Route::get('/cron/run', function (Request $request) {
    abort_unless($request->query('key') === config('app.cron_key'), 403);
    Artisan::call('schedule:run');

    return response('OK '.now()->toDateTimeString(), 200, ['Content-Type' => 'text/plain']);
})->name('cron.run');

// Wasmer Edge cron endpoints (also usable on any stateless host)
Route::get('/cron/run-schedule', function (Request $request) {
    abort_unless($request->query('key') === config('app.cron_key'), 403);
    Artisan::call('schedule:run');

    return response('OK '.now()->toDateTimeString(), 200, ['Content-Type' => 'text/plain']);
})->name('cron.run-schedule');

Route::get('/cron/medical-reminders', function (Request $request) {
    abort_unless($request->query('key') === config('app.cron_key'), 403);
    dispatch_sync(new SendMedicalReminders);

    return response('OK', 200, ['Content-Type' => 'text/plain']);
})->name('cron.medical-reminders');

Route::get('/cron/weekly-backup', function (Request $request) {
    abort_unless($request->query('key') === config('app.cron_key'), 403);
    dispatch_sync(new WeeklyBackup);

    return response('OK', 200, ['Content-Type' => 'text/plain']);
})->name('cron.weekly-backup');

// Public voting (token-based, no login required)
Route::get('/vote/{token}', [VotePublicController::class, 'show'])->name('vote.show');
Route::post('/vote/{token}', [VotePublicController::class, 'cast'])->name('vote.cast');
