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
use App\Http\Controllers\Admin\NewsletterController;
use App\Http\Controllers\Admin\PartnershipController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\SeasonController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ThumbnailController;
use App\Http\Controllers\Admin\TrialRequestController;
use App\Http\Controllers\Admin\VoteController;
use App\Http\Controllers\DiveDataController;
use App\Http\Controllers\HomepageLayoutController;
use App\Http\Controllers\ProfileController;
use App\Models\User;
use App\Services\UpdateService;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;

// Members management
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

// Articles & Links
Route::resource('articles', ArticleController::class)->except('show');
Route::post('articles/{article}/translate', [ArticleController::class, 'translate'])->name('articles.translate');
Route::resource('links', LinkController::class)->only(['index', 'store', 'destroy']);

// Newsletters
Route::resource('newsletters', NewsletterController::class)->except('show');
Route::get('newsletters/{newsletter}', [NewsletterController::class, 'show'])->name('newsletters.show');
Route::post('newsletters/{newsletter}/submit', [NewsletterController::class, 'submit'])->name('newsletters.submit');
Route::post('newsletters/{newsletter}/withdraw', [NewsletterController::class, 'withdraw'])->name('newsletters.withdraw');
Route::post('newsletters/{newsletter}/send', [NewsletterController::class, 'send'])->name('newsletters.send');
Route::get('newsletters/{newsletter}/preview-email', [NewsletterController::class, 'preview'])->name('newsletters.preview-email');
Route::get('newsletters/{newsletter}/test-send', [NewsletterController::class, 'testSend'])->name('newsletters.test-send');

// Document Library
Route::get('/library', [LibraryController::class, 'index'])->name('library.index');
Route::post('/library/upload', [LibraryController::class, 'upload'])->name('library.upload');
Route::put('/library/{file}', [LibraryController::class, 'update'])->name('library.update');
Route::delete('/library/{file}', [LibraryController::class, 'destroy'])->name('library.destroy');
Route::get('/library/{file}/download', [LibraryController::class, 'download'])->name('library.download');
Route::get('/library/download-zip', [LibraryController::class, 'downloadZip'])->name('library.download-zip');
Route::get('/library/{file}/thumb', [ThumbnailController::class, 'show'])->name('library.thumb');
Route::post('/library/folder', [LibraryController::class, 'createFolder'])->name('library.create-folder');

// Audit Logs
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
Route::post('/system/update', function () {
    abort_unless(auth()->user()->isBureauMaster(), 403);
    $result = UpdateService::applyUpdate();

    return back()->with($result['success'] ? 'success' : 'error', $result['message']);
})->name('system.update');

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
Route::post('/payments/generate-bulk', [PaymentController::class, 'generateBulkFees'])->name('payments.generate-bulk');
Route::put('/payments/{payment}/adjust', [PaymentController::class, 'adjustComponents'])->name('payments.adjust');
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
Route::post('/equipment/quick-loan', [EquipmentController::class, 'quickLoan'])->name('equipment.quick-loan');
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

// Club Partnerships
Route::get('/partnerships', [PartnershipController::class, 'index'])->name('partnerships.index');
Route::get('/partnerships/create', [PartnershipController::class, 'create'])->name('partnerships.create');
Route::post('/partnerships', [PartnershipController::class, 'store'])->name('partnerships.store');
Route::delete('/partnerships/{partnership}', [PartnershipController::class, 'destroy'])->name('partnerships.destroy');
Route::get('/partnerships/{partnership}/remote-events', [PartnershipController::class, 'remoteEvents'])->name('partnerships.remote-events');
Route::get('/partnerships/registrations', [PartnershipController::class, 'registrations'])->name('partnerships.registrations');
Route::post('/partnerships/registrations/{registration}/approve', [PartnershipController::class, 'approveRegistration'])->name('partnerships.registrations.approve');
Route::post('/partnerships/registrations/{registration}/reject', [PartnershipController::class, 'rejectRegistration'])->name('partnerships.registrations.reject');
