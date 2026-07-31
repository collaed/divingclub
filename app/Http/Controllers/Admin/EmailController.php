<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Services\ArticleTranslationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EmailController extends Controller
{
    use PaginatesFromRequest;

    public function index(): JsonResponse|RedirectResponse|View
    {
        $templates = EmailTemplate::orderBy('name')->get();
        $log = EmailLog::orderByDesc('created_at')->paginate($this->perPage(30));

        return view('admin.email.index', compact('templates', 'log'));
    }

    public function storeTemplate(Request $request): JsonResponse|RedirectResponse
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:email_templates,slug',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'locale' => 'required|string|max:5',
        ]);
        EmailTemplate::create($v);

        return back()->with('success', __('Template created.'));
    }

    public function updateTemplate(Request $request, EmailTemplate $template): JsonResponse|RedirectResponse
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);
        $template->update($v);

        return back()->with('success', __('Template updated.'));
    }

    public function destroyTemplate(EmailTemplate $template): JsonResponse|RedirectResponse
    {
        $template->delete();

        return back()->with('success', __('Template deleted.'));
    }

    public function preview(Request $request): JsonResponse|RedirectResponse
    {
        $template = EmailTemplate::findOrFail($request->template_id);
        $user = User::with('detail')->first();
        $rendered = $this->renderTemplate($template, $user);

        return response()->json($rendered);
    }

    public function send(Request $request): RedirectResponse
    {
        $request->validate([
            'template_id' => 'required|exists:email_templates,id',
            'group' => 'required|in:all,active,instructors,bureau,expiring_certs,unpaid,event',
            'event_id' => 'nullable|required_if:group,event|exists:events,id',
        ]);

        $template = EmailTemplate::findOrFail($request->template_id);
        $users = $this->resolveGroup($request->group, $request->event_id);
        $sourceLocale = $template->locale ?? 'fr';

        // Pre-translate subject+body per unique target locale
        $translations = []; // locale => ['subject' => ..., 'body' => ...]
        $translator = app(ArticleTranslationService::class);
        $locales = $users->pluck('preferred_locale')->filter()->unique()->reject(fn ($l): bool => $l === $sourceLocale);

        foreach ($locales as $locale) {
            $translations[$locale] = [
                'subject' => $translator->translateText($template->subject, $sourceLocale, $locale) ?? $template->subject,
                'body' => $translator->translateText($template->body, $sourceLocale, $locale) ?? $template->body,
            ];
        }

        // Batch by locale for efficient sending
        $sent = 0;
        foreach ($users as $user) {
            $rendered = $this->renderTemplate($template, $user);
            $userLocale = $user->preferred_locale;

            // Append translated version if user has a different preferred language
            if ($userLocale && $userLocale !== $sourceLocale && isset($translations[$userLocale])) {
                $t = $translations[$userLocale];
                $tRendered = $this->renderVars(
                    ['subject' => $t['subject'], 'body' => $t['body']],
                    $user
                );
                $rendered['body'] .= "\n\n--- ".strtoupper($userLocale)." ---\n\n".$tRendered['body'];
            }

            EmailLog::create([
                'event_id' => $request->event_id,
                'user_id' => $user->id,
                'to_email' => $user->primary_email,
                'subject' => $rendered['subject'],
                'body' => $rendered['body'],
                'template_slug' => $template->slug,
                'status' => 'queued',
            ]);
            $sent++;
        }

        // Dispatch actual sending via queue
        dispatch(function (): void {
            $queued = EmailLog::where('status', 'queued')->get();
            foreach ($queued as $log) {
                if (config('app.staging_mode')) {
                    $log->update(['status' => 'staging_captured']);

                    continue;
                }
                try {
                    Mail::raw($log->body, fn ($m) => $m->to($log->to_email)->subject($log->subject));
                    $log->update(['status' => 'sent', 'attempts' => $log->attempts + 1]);
                } catch (\Exception $e) {
                    $log->update(['status' => $log->attempts >= 2 ? 'failed' : 'queued', 'attempts' => $log->attempts + 1, 'error' => $e->getMessage()]);
                }
            }
        })->afterResponse();

        return back()->with('success', __(':count emails queued.', ['count' => $sent]));
    }

    private function renderTemplate(EmailTemplate $template, User $user): array
    {
        return $this->renderVars(['subject' => $template->subject, 'body' => $template->body], $user);
    }

    private function renderVars(array $texts, User $user): array
    {
        $vars = [
            '{{first_name}}' => $user->detail?->first_name ?? '',
            '{{last_name}}' => $user->detail?->last_name ?? '',
            '{{name}}' => $user->name,
            '{{email}}' => $user->primary_email,
            '{{club_name}}' => ThemeSetting::get('club_full_name', 'Diving Club'),
        ];

        return [
            'subject' => str_replace(array_keys($vars), array_values($vars), $texts['subject']),
            'body' => str_replace(array_keys($vars), array_values($vars), $texts['body']),
        ];
    }

    private function resolveGroup(string $group, ?int $eventId = null)
    {
        return match ($group) {
            'all' => User::with('detail')->whereNotNull('email_verified_at')->get(),
            'active' => User::with('detail')->whereHas('status', fn ($q) => $q->where('slug', 'actif'))->get(),
            'instructors' => User::with('detail')->role('instructor')->get(),
            'bureau' => User::with('detail')->role(['bureau_master', 'bureau_finance', 'bureau_technical'])->get(),
            'expiring_certs' => User::with('detail')->whereHas('documents', fn ($q) => $q->where('category', 'medical')->where('is_current', true)->whereBetween('expiry_date', [now(), now()->addDays(30)]))->get(),
            'unpaid' => User::with('detail')->whereHas('paymentsExpected', fn ($q) => $q->where('status', 'pending'))->get(),
            'event' => $eventId ? User::with('detail')->whereHas('eventRegistrations', fn ($q) => $q->where('event_id', $eventId)->where('status', 'confirmed'))->get() : collect(),
            default => collect(),
        };
    }

    public function approve(EmailLog $emailLog): RedirectResponse
    {
        abort_unless(auth()->user()->can('send email'), 403);
        $emailLog->update(['status' => 'forwarded', 'authorized' => true, 'error' => null]);

        return back()->with('success', __('Communication approved.'));
    }

    public function reject(EmailLog $emailLog): RedirectResponse
    {
        abort_unless(auth()->user()->can('send email'), 403);
        $emailLog->update(['status' => 'rejected', 'authorized' => false]);

        return back()->with('success', __('Communication rejected.'));
    }

    public function destroyLog(EmailLog $emailLog): RedirectResponse
    {
        abort_unless(auth()->user()->can('send email'), 403);
        $emailLog->delete();

        return back()->with('success', __('Communication deleted.'));
    }
}
