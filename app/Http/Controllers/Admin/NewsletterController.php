<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\EmailLog;
use App\Models\Newsletter;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Services\ArticleTranslationService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class NewsletterController extends Controller
{
    public function index(): RedirectResponse|View
    {
        $newsletters = Newsletter::with('creator', 'approvals.user')
            ->orderByDesc('created_at')->paginate(20);

        return view('admin.newsletters.index', compact('newsletters'));
    }

    public function create(): RedirectResponse|View
    {
        $articles = Article::active()->where('article_type', '!=', 'classified')
            ->orderByDesc('created_at')->limit(50)->get();

        return view('admin.newsletters.compose', [
            'newsletter' => null,
            'articles' => $articles,
        ]);
    }

    public function store(Request $request): RedirectResponse|View
    {
        $v = $request->validate([
            'title' => 'required|string|max:255',
            'month' => 'required|string|max:7',
            'background_image' => 'nullable|image|max:5120',
            'background_preset' => 'nullable|string|max:50',
            'slots' => 'required|array|min:1',
            'slots.*.position' => 'required|integer|between:1,5',
            'slots.*.article_id' => 'required|exists:articles,id',
            'slots.*.article_type' => 'nullable|string|max:30',
            'slots.*.teaser' => 'nullable|string|max:500',
            'slots.*.custom_url' => 'nullable|string|max:500',
            'slots.*.slug' => 'nullable|string|max:255',
        ]);

        $bg = $request->input('background_preset', 'default-bulles');
        if ($request->hasFile('background_image')) {
            $bg = $request->file('background_image')->store('newsletters', 'public');
        }

        $newsletter = Newsletter::create([
            'title' => $v['title'],
            'month' => $v['month'],
            'background_image' => $bg,
            'slots' => $v['slots'],
            'decorations' => $request->input('decorations') ? json_decode($request->input('decorations'), true) : null,
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('admin.newsletters.show', $newsletter)
            ->with('success', __('Newsletter draft saved.'));
    }

    public function show(Newsletter $newsletter): RedirectResponse|View
    {
        $newsletter->load('approvals.user.detail', 'creator.detail');
        $slotArticles = $newsletter->slotArticles();

        return view('admin.newsletters.show', compact('newsletter', 'slotArticles'));
    }

    public function edit(Newsletter $newsletter): RedirectResponse|View
    {
        abort_if($newsletter->status === 'sent', 403, 'Cannot edit a sent newsletter.');

        $articles = Article::active()->where('article_type', '!=', 'classified')
            ->orderByDesc('created_at')->limit(50)->get();

        return view('admin.newsletters.compose', compact('newsletter', 'articles'));
    }

    public function update(Request $request, Newsletter $newsletter): RedirectResponse
    {
        abort_if($newsletter->status === 'sent', 403);

        $v = $request->validate([
            'title' => 'required|string|max:255',
            'month' => 'required|string|max:7',
            'background_image' => 'nullable|image|max:5120',
            'background_preset' => 'nullable|string|max:50',
            'slots' => 'required|array|min:1',
            'slots.*.position' => 'required|integer|between:1,5',
            'slots.*.article_id' => 'required|exists:articles,id',
            'slots.*.article_type' => 'nullable|string|max:30',
            'slots.*.teaser' => 'nullable|string|max:500',
            'slots.*.custom_url' => 'nullable|string|max:500',
            'slots.*.slug' => 'nullable|string|max:255',
        ]);

        $bg = $request->input('background_preset') ?: $newsletter->background_image;
        if ($request->hasFile('background_image')) {
            $bg = $request->file('background_image')->store('newsletters', 'public');
        }

        $newsletter->update([
            'title' => $v['title'],
            'month' => $v['month'],
            'background_image' => $bg,
            'slots' => $v['slots'],
            'decorations' => $request->input('decorations') ? json_decode($request->input('decorations'), true) : null,
            'status' => 'draft',
        ]);
        $newsletter->approvals()->delete();

        return redirect()->route('admin.newsletters.show', $newsletter)
            ->with('success', __('Newsletter updated.'));
    }

    public function submit(Newsletter $newsletter): RedirectResponse
    {
        abort_unless($newsletter->status === 'draft', 403);
        $newsletter->update(['status' => 'pending']);

        return back()->with('success', __('Newsletter submitted for bureau approval.'));
    }

    public function withdraw(Newsletter $newsletter): RedirectResponse
    {
        abort_unless($newsletter->status === 'pending', 403);
        abort_unless($newsletter->created_by === auth()->id(), 403);
        $newsletter->approvals()->delete();
        $newsletter->update(['status' => 'draft']);

        return redirect()->route('admin.newsletters.edit', $newsletter)->with('success', __('Newsletter withdrawn to draft.'));
    }

    public function approve(Request $request, Newsletter $newsletter): RedirectResponse
    {
        abort_unless($newsletter->status === 'pending', 403);
        $user = auth()->user();

        // Creator cannot approve their own
        if ($newsletter->created_by === $user->id) {
            return back()->with('error', __('You cannot approve your own newsletter.'));
        }

        $newsletter->approvals()->updateOrCreate(
            ['user_id' => $user->id],
            ['approved' => true, 'comment' => $request->input('comment')]
        );

        if ($newsletter->approvalCount() >= 3) {
            $newsletter->update(['status' => 'approved']);
        }

        return back()->with('success', __('Approval recorded (:n/3).', ['n' => $newsletter->approvalCount()]));
    }

    public function send(Newsletter $newsletter): RedirectResponse
    {
        abort_unless($newsletter->status === 'approved', 403);

        $slotArticles = $newsletter->slotArticles();
        config('app.url');
        ThemeSetting::get('club_full_name', 'Diving Club');
        app(ArticleTranslationService::class);

        // Build the email HTML
        $users = User::whereNotNull('email_verified_at')->with('detail')->get();

        foreach ($users as $user) {
            $locale = $user->preferred_locale ?? 'fr';

            $html = $this->renderEmailHtml($newsletter, $slotArticles, 'fr');

            // Append translated version if user prefers another language
            if ($locale !== 'fr') {
                $html .= '<hr style="margin:30px 0;border-color:#ccc">';
                $html .= '<p style="text-align:center;color:#666;font-size:12px">— '.strtoupper($locale).' —</p>';
                $html .= $this->renderEmailHtml($newsletter, $slotArticles, $locale);
            }

            EmailLog::create([
                'user_id' => $user->id,
                'to_email' => $user->primary_email,
                'subject' => $newsletter->title,
                'body' => $html,
                'template_slug' => 'newsletter-'.$newsletter->id,
                'status' => 'queued',
            ]);
        }

        // Dispatch sending
        dispatch(function (): void {
            $queued = EmailLog::where('status', 'queued')->get();

            foreach ($queued as $log) {
                if (config('app.staging_mode') && ! config('app.staging_use_smtp')) {
                    $log->update(['status' => 'staging_captured']);

                    continue;
                }
                try {
                    Mail::html($log->body, fn ($m) => $m->to($log->to_email)->subject($log->subject));
                    $log->update(['status' => 'sent', 'attempts' => $log->attempts + 1]);
                } catch (\Exception $e) {
                    $log->update([
                        'status' => $log->attempts >= 2 ? 'failed' : 'queued',
                        'attempts' => $log->attempts + 1,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        })->afterResponse();

        $newsletter->update(['status' => 'sent', 'sent_at' => now()]);

        return redirect()->route('admin.newsletters.index')
            ->with('success', __('Newsletter sent to :count members.', ['count' => $users->count()]));
    }

    public function destroy(Newsletter $newsletter): RedirectResponse|View
    {
        abort_if($newsletter->status === 'sent', 403);
        $newsletter->delete();

        return redirect()->route('admin.newsletters.index')
            ->with('success', __('Newsletter deleted.'));
    }

    /** Render email preview in an iframe-friendly standalone page. */
    public function preview(Newsletter $newsletter): RedirectResponse|View
    {
        $data = $this->buildEmailData($newsletter, 'fr');

        return view('admin.newsletters.themes.email', $data);
    }

    /** Send a test email to the current user. */
    public function testSend(Newsletter $newsletter): RedirectResponse|View
    {
        $data = $this->buildEmailData($newsletter, 'fr');
        $html = view('admin.newsletters.themes.email', $data)->render();
        $to = auth()->user()->primary_email;

        Mail::html($html, fn ($m) => $m->to($to)->subject('[TEST] '.$newsletter->title));

        return back()->with('success', __('Test email sent to :email', ['email' => $to]));
    }

    private function renderEmailHtml(Newsletter $newsletter, array $slotArticles, string $locale): string
    {
        $data = $this->buildEmailData($newsletter, $locale, $slotArticles);

        return view('admin.newsletters.themes.email', $data)->render();
    }

    /** Build the data array for the email Blade template. */
    private function buildEmailData(Newsletter $newsletter, string $locale, ?array $slotArticles = null): array
    {
        $slotArticles = $slotArticles ?? $newsletter->slotArticles();
        $theme = $newsletter->background_image ?? 'default-bulles';
        $themeFolder = match (true) {
            str_contains($theme, 'bulles') => 'bulles',
            str_contains($theme, 'abyss') => 'abyss',
            str_contains($theme, 'coral') => 'coral',
            str_contains($theme, 'arctic') => 'arctic',
            default => 'bulles',
        };

        $monthLabel = '';
        if ($newsletter->month) {
            try {
                $monthLabel = Carbon::createFromFormat('Y-m', $newsletter->month)
                    ->locale($locale)->isoFormat('MMMM YYYY');
            } catch (\Throwable) {
                $monthLabel = $newsletter->month;
            }
        }

        return [
            'newsletter' => $newsletter,
            'slotArticles' => $slotArticles,
            'locale' => $locale,
            'appUrl' => config('app.url'),
            'articleBaseUrl' => ThemeSetting::get('newsletter_article_base_url') ?: config('app.url'),
            'clubName' => ThemeSetting::get('club_full_name', 'Diving Club'),
            'theme' => $themeFolder,
            'monthLabel' => $monthLabel,
            'unsubscribeUrl' => null,
            'decorations' => $newsletter->decorations ?? [],
        ];
    }
}
