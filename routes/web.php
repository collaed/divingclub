<?php

use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\NewsletterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\BuddyController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\ClassifiedController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactMemberController;
use App\Http\Controllers\DiveDataController;
use App\Http\Controllers\DiveGroupController;
use App\Http\Controllers\DocumentBrowserController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\GdprController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\InstructorAvailabilityController;
use App\Http\Controllers\MembersDirectoryController;
use App\Http\Controllers\ProfileAvatarController;
use App\Http\Controllers\ProfileCertificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileDocumentController;
use App\Http\Controllers\ProfileEmailController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\StagingMailController;
use App\Http\Controllers\TrialController;
use App\Http\Controllers\TripSettlementController;
use App\Http\Controllers\VotePublicController;
use App\Http\Middleware\CheckLicense;
use App\Models\EventPhoto;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
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
Route::get('/health', HealthController::class)->name('health');
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/home2', [HomeController::class, 'index2'])->name('home2');
Route::get('/home3', [HomeController::class, 'index3'])->name('home3');
Route::get('/home4', [HomeController::class, 'index4'])->name('home4');
Route::get('/article/{slug}', [HomeController::class, 'showArticle'])->name('article.show');
Route::get('/trial', [TrialController::class, 'show'])->name('trial.show');
Route::post('/trial', [TrialController::class, 'store'])->middleware('throttle:3,1')->name('trial.store');
Route::get('/dues', fn () => view('cotisation', ['cfg' => config('cotisation')]))->name('dues.show');
Route::get('/cotisation', fn () => redirect()->route('dues.show'))->name('cotisation');
Route::get('/qr/sepa-public', [QrCodeController::class, 'sepaPublic'])->name('qr.sepa.public');
Route::get('/qr/payment', [QrCodeController::class, 'signedPaymentQr'])->name('qr.payment.signed');
Route::get('/pay/verify', [QrCodeController::class, 'verifyPayment'])->name('payment.verify');
Route::get('/calendar.ics', [CalendarFeedController::class, 'ical'])->name('calendar.ics');
Route::get('/contact', fn () => view('contact'))->name('contact');
Route::post('/contact', [ContactController::class, 'send'])->middleware('throttle:5,1')->name('contact.send');

// Guest auth
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->middleware(CheckLicense::class)->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware([CheckLicense::class, 'throttle:5,1']);
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');

    // Password reset
    Route::get('/forgot-password', fn () => view('auth.forgot-password'))->name('password.request');
    Route::post('/forgot-password', function (Request $request) {
        $request->validate(['email' => 'required|email']);
        Log::info('Password reset requested', ['email' => $request->email, 'ip' => $request->ip()]);
        Password::sendResetLink(['email' => $request->email]);

        return back()->with('success', __('Reset link sent if the email exists.'));
    })->middleware('throttle:5,1')->name('password.email');
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
    })->middleware('throttle:3,1')->name('password.update');
});

// EU Login (CAS) — must be before the {provider} wildcard

// OAuth
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('auth.social.redirect');
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('auth.social.callback');
Route::post('/auth/social/confirm-link', [SocialAuthController::class, 'confirmLink'])->middleware('auth')->name('auth.social.confirm-link');
Route::post('/auth/social/dismiss-link', [SocialAuthController::class, 'dismissLink'])->middleware('auth')->name('auth.social.dismiss-link');
Route::get('/auth/social/choose', [SocialAuthController::class, 'choose'])->name('auth.social.choose');
Route::post('/auth/social/link-existing', [SocialAuthController::class, 'linkExisting'])->name('auth.social.link-existing');
Route::post('/auth/social/create-new', [SocialAuthController::class, 'createNew'])->name('auth.social.create-new');

// Email verification
Route::middleware('auth')->group(function () {
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');

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
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.update.password');
    Route::post('/profile/diving', [ProfileController::class, 'updateDiving'])->name('profile.update.diving');
    Route::post('/profile/federation-key/{licence}', [ProfileController::class, 'updateFederationKey'])->name('profile.update.federation-key');
    Route::post('/profile/licence/{licence}', [ProfileController::class, 'updateLicence'])->name('profile.update.licence');
    Route::post('/profile/language', [ProfileController::class, 'updateLanguage'])->name('profile.update.language');
    Route::post('/profile/document', [ProfileDocumentController::class, 'upload'])->name('profile.document.upload');
    Route::get('/profile/document/{document}', [ProfileDocumentController::class, 'download'])->name('profile.document.download');
    Route::get('/profile/document/{document}/view', [ProfileDocumentController::class, 'view'])->name('profile.document.view');
    Route::post('/profile/document/{document}/verify', [ProfileDocumentController::class, 'verify'])->name('profile.document.verify');

    Route::post('/profile/avatar', [ProfileAvatarController::class, 'upload'])->name('profile.avatar.upload');
    Route::delete('/profile/avatar', [ProfileAvatarController::class, 'delete'])->name('profile.avatar.delete');

    // Certification levels
    Route::post('/profile/cert', [ProfileCertificationController::class, 'add'])->name('profile.cert.add');
    Route::put('/profile/cert/{certLevel}', [ProfileCertificationController::class, 'update'])->name('profile.cert.update');
    Route::post('/profile/cert/{certLevel}/primary', [ProfileCertificationController::class, 'setPrimary'])->name('profile.cert.primary');
    Route::delete('/profile/cert/{certLevel}', [ProfileCertificationController::class, 'remove'])->name('profile.cert.remove');

    // Members directory (visible to all authenticated users)
    Route::get('/members', [MembersDirectoryController::class, 'directory'])->name('members.directory');
    Route::get('/members/trombinoscope', [MembersDirectoryController::class, 'trombinoscope'])->name('members.trombinoscope');
    Route::get('/members/{user}/profile', [ProfileController::class, 'show'])->name('members.profile');

    // Contact member (no email exposed)
    Route::get('/members/{user}/contact', [ContactMemberController::class, 'create'])->name('contact.member');
    Route::post('/members/{user}/contact', [ContactMemberController::class, 'store'])->middleware('throttle:10,1')->name('contact.member.send');

    // Document browser (role-based visibility, upload for instructors/bureau)
    Route::get('/gallery', [DocumentBrowserController::class, 'gallery'])->name('gallery');
    Route::get('/photos/browse', function () {
        $user = auth()->user();
        $query = EventPhoto::where('approved', true)->where('gdpr_consent', true);

        if (! $user) {
            // Public: no faces only
            $query->where(fn ($q) => $q->where('has_faces', false)->orWhereNull('has_faces'));
        } else {
            // Authenticated: include own photos regardless of face detection
            $query->where(fn ($q) => $q
                ->where(fn ($q2) => $q2->where('has_faces', false)->orWhereNull('has_faces'))
                ->orWhere('uploaded_by', $user->id)
            );
        }

        $photos = $query->orderByDesc('quality_score')->limit(100)
            ->get()->map(fn ($p) => asset('storage/'.$p->path));

        return response()->json($photos);
    })->name('photos.browse');
    Route::get('/gallery/{event}', [DocumentBrowserController::class, 'galleryEvent'])->name('gallery.event');
    Route::post('/gallery/{event}/upload', [DocumentBrowserController::class, 'galleryUpload'])->name('gallery.upload');
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
    Route::post('/profile/email', [ProfileEmailController::class, 'add'])->name('profile.email.add');
    Route::post('/profile/email/{email}/primary', [ProfileEmailController::class, 'setPrimary'])->name('profile.email.primary');
    Route::delete('/profile/email/{email}', [ProfileEmailController::class, 'delete'])->name('profile.email.delete');
    Route::post('/profile/email/{email}/toggle-mail', [ProfileEmailController::class, 'toggleReceiveMail'])->name('profile.email.toggle-mail');

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

    // Instructor Availability (read-only for all members, editable for instructors/bureau)
    Route::get('/availability', [InstructorAvailabilityController::class, 'index'])->name('availability.index');
    Route::middleware('role:bureau_master,bureau_finance,bureau_technical,instructor,instructor_apnea')->group(function () {
        Route::post('/availability/toggle', [InstructorAvailabilityController::class, 'toggle'])->name('availability.toggle');
    });

    // Newsletter approval (all bureau roles)
    Route::middleware('role:bureau_master,bureau_finance,bureau_technical')->prefix('admin')->name('admin.')->group(function () {
        Route::get('newsletters/{newsletter}/review', [NewsletterController::class, 'show'])->name('newsletters.review');
        Route::post('newsletters/{newsletter}/approve', [NewsletterController::class, 'approve'])->name('newsletters.approve');
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
    Route::post('/events/{event}/register', [EventController::class, 'register'])->middleware('throttle:10,1')->name('events.register');
    Route::post('/events/{event}/cancel-registration', [EventController::class, 'cancelRegistration'])->middleware('throttle:10,1')->name('events.cancel-registration');
    Route::post('/events/{event}/update-comment', [EventController::class, 'updateComment'])->name('events.update-comment');
    Route::post('/events/{event}/cancel', [EventController::class, 'cancel'])->name('events.cancel');
    Route::post('/events/{event}/photos', [EventController::class, 'uploadPhoto'])->name('events.photo.upload');
    Route::delete('/events/{event}/photos/{photo}', [EventController::class, 'deletePhoto'])->name('events.photo.delete');

    // Dive groups (palanquées)
    Route::get('/events/{event}/dive-groups', [DiveGroupController::class, 'index'])->name('events.dive-groups');
    Route::post('/events/{event}/dive-groups', [DiveGroupController::class, 'store'])->name('events.dive-groups.store');
    Route::post('/dive-groups/{group}/members', [DiveGroupController::class, 'addMember'])->name('dive-groups.add-member');
    Route::delete('/dive-group-members/{member}', [DiveGroupController::class, 'removeMember'])->name('dive-groups.remove-member');
    Route::post('/dive-group-members/{member}/toggle-leader', [DiveGroupController::class, 'toggleLeader'])->name('dive-groups.toggle-leader');
    Route::delete('/dive-groups/{group}', [DiveGroupController::class, 'destroy'])->name('dive-groups.destroy');
    Route::get('/events/{event}/dive-groups/validate', [DiveGroupController::class, 'validateGroups'])->name('events.dive-groups.validate');
    Route::get('/events/{event}/dive-groups/propose', [DiveGroupController::class, 'propose'])->name('events.dive-groups.propose');
    Route::post('/events/{event}/dive-groups/apply-proposal', [DiveGroupController::class, 'applyProposal'])->name('events.dive-groups.apply-proposal');
    Route::get('/events/{event}/dive-groups/suggest-swaps', [DiveGroupController::class, 'suggestSwaps'])->name('events.dive-groups.suggest-swaps');
    Route::get('/events/{event}/dive-groups/print', [DiveGroupController::class, 'printFiche'])->name('events.dive-groups.print');

    // Trip settlement
    Route::get('/events/{event}/settlement', [TripSettlementController::class, 'show'])->name('events.settlement');
    Route::post('/events/{event}/settlement/receipts', [TripSettlementController::class, 'storeReceipt'])->name('events.settlement.store-receipt');
    Route::delete('/events/{event}/settlement/receipts/{receipt}', [TripSettlementController::class, 'deleteReceipt'])->name('events.settlement.delete-receipt');
    Route::get('/events/{event}/settlement/receipts/{receipt}/image', [TripSettlementController::class, 'receiptImage'])->name('events.settlement.receipt-image');
    Route::get('/events/{event}/settlement/manage', [TripSettlementController::class, 'manage'])->name('events.settlement.manage');
    Route::post('/events/{event}/settlement/receipts/{receipt}/approve', [TripSettlementController::class, 'approveReceipt'])->name('events.settlement.approve');
    Route::post('/events/{event}/settlement/receipts/{receipt}/reject', [TripSettlementController::class, 'rejectReceipt'])->name('events.settlement.reject');
    Route::post('/events/{event}/settlement/bureau-receipt', [TripSettlementController::class, 'bureauReceipt'])->name('events.settlement.bureau-receipt');
    Route::post('/events/{event}/settlement/update-vans', [TripSettlementController::class, 'updateVans'])->name('events.settlement.update-vans');
    Route::post('/events/{event}/settlement/participants/{participant}', [TripSettlementController::class, 'updateParticipant'])->name('events.settlement.update-participant');
    Route::post('/events/{event}/settlement/close', [TripSettlementController::class, 'closeLedger'])->name('events.settlement.close');
    Route::post('/events/{event}/settlement/reopen', [TripSettlementController::class, 'reopenLedger'])->name('events.settlement.reopen');
    Route::get('/events/{event}/settlement/breakdown', [TripSettlementController::class, 'breakdown'])->name('events.settlement.breakdown');

    // Stop impersonation (must be outside bureau_master group — user is impersonated)
    Route::get('/admin/stop-impersonation', [MemberController::class, 'stopImpersonation'])->name('admin.stop-impersonation');

    // Admin routes (all bureau roles)
    Route::middleware('role:bureau_master,bureau_finance,bureau_technical')->prefix('admin')->name('admin.')->group(
        base_path('routes/admin.php')
    );
});

// Offline page for PWA
Route::get('/offline', fn () => view('offline'))->name('offline');

// Staging mail viewer (only active when APP_ENV is not production)
if (app()->environment('local', 'staging', 'acceptance', 'testing')) {
    Route::prefix('staging-mail')->middleware('auth')->group(function () {
        Route::get('/', [StagingMailController::class, 'index'])->name('staging.mail.index');
        Route::get('/{mail}', [StagingMailController::class, 'show'])->name('staging.mail.show');
        Route::get('/{mail}/raw', [StagingMailController::class, 'raw'])->name('staging.mail.raw');
        Route::delete('/', [StagingMailController::class, 'clear'])->name('staging.mail.clear');
    });
}

// Public voting (token-based, no login required)
Route::get('/vote/{token}', [VotePublicController::class, 'show'])->name('vote.show');
Route::post('/vote/{token}', [VotePublicController::class, 'cast'])->middleware('throttle:10,1')->name('vote.cast');
