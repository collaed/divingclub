<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\EmailLog;
use App\Models\Newsletter;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Services\ArticleTranslationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NewsletterController extends Controller
{
    public function index()
    {
        $newsletters = Newsletter::with('creator', 'approvals.user')
            ->orderByDesc('created_at')->paginate(20);

        return view('admin.newsletters.index', compact('newsletters'));
    }

    public function create()
    {
        $articles = Article::active()->where('article_type', '!=', 'classified')
            ->orderByDesc('created_at')->limit(50)->get();

        return view('admin.newsletters.compose', [
            'newsletter' => null,
            'articles' => $articles,
        ]);
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'title' => 'required|string|max:255',
            'month' => 'required|string|max:7',
            'background_image' => 'nullable|image|max:5120',
            'slots' => 'required|array|min:1',
            'slots.*.position' => 'required|integer|between:1,5',
            'slots.*.article_id' => 'required|exists:articles,id',
        ]);

        $bgPath = null;
        if ($request->hasFile('background_image')) {
            $bgPath = $request->file('background_image')->store('newsletters', 'public');
        }

        $newsletter = Newsletter::create([
            'title' => $v['title'],
            'month' => $v['month'],
            'background_image' => $bgPath,
            'slots' => $v['slots'],
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('admin.newsletters.show', $newsletter)
            ->with('success', __('Newsletter draft saved.'));
    }

    public function show(Newsletter $newsletter)
    {
        $newsletter->load('approvals.user', 'creator');
        $slotArticles = $newsletter->slotArticles();

        return view('admin.newsletters.show', compact('newsletter', 'slotArticles'));
    }

    public function edit(Newsletter $newsletter)
    {
        abort_if($newsletter->status === 'sent', 403, 'Cannot edit a sent newsletter.');

        $articles = Article::active()->where('article_type', '!=', 'classified')
            ->orderByDesc('created_at')->limit(50)->get();

        return view('admin.newsletters.compose', compact('newsletter', 'articles'));
    }

    public function update(Request $request, Newsletter $newsletter)
    {
        abort_if($newsletter->status === 'sent', 403);

        $v = $request->validate([
            'title' => 'required|string|max:255',
            'month' => 'required|string|max:7',
            'background_image' => 'nullable|image|max:5120',
            'slots' => 'required|array|min:1',
            'slots.*.position' => 'required|integer|between:1,5',
            'slots.*.article_id' => 'required|exists:articles,id',
        ]);

        if ($request->hasFile('background_image')) {
            $newsletter->background_image = $request->file('background_image')->store('newsletters', 'public');
        }

        $newsletter->update([
            'title' => $v['title'],
            'month' => $v['month'],
            'background_image' => $newsletter->background_image,
            'slots' => $v['slots'],
            'status' => 'draft', // reset approvals on edit
        ]);
        $newsletter->approvals()->delete();

        return redirect()->route('admin.newsletters.show', $newsletter)
            ->with('success', __('Newsletter updated.'));
    }

    public function submit(Newsletter $newsletter)
    {
        abort_unless($newsletter->status === 'draft', 403);
        $newsletter->update(['status' => 'pending']);

        return back()->with('success', __('Newsletter submitted for bureau approval.'));
    }

    public function approve(Request $request, Newsletter $newsletter)
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

    public function send(Newsletter $newsletter)
    {
        abort_unless($newsletter->status === 'approved', 403);

        $slotArticles = $newsletter->slotArticles();
        $appUrl = config('app.url');
        $clubName = ThemeSetting::get('club_full_name', 'Diving Club');
        $translator = app(ArticleTranslationService::class);

        // Build the email HTML
        $users = User::whereNotNull('email_verified_at')->with('detail')->get();

        foreach ($users as $user) {
            $locale = $user->preferred_locale ?? 'fr';

            $html = $this->renderEmailHtml($newsletter, $slotArticles, 'fr', $appUrl, $clubName);

            // Append translated version if user prefers another language
            if ($locale !== 'fr') {
                $html .= '<hr style="margin:30px 0;border-color:#ccc">';
                $html .= '<p style="text-align:center;color:#666;font-size:12px">— '.strtoupper($locale).' —</p>';
                $html .= $this->renderEmailHtml($newsletter, $slotArticles, $locale, $appUrl, $clubName);
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
        dispatch(function () {
            $queued = EmailLog::where('status', 'queued')->get();
            foreach ($queued as $log) {
                if (config('app.staging_mode')) {
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

    public function destroy(Newsletter $newsletter)
    {
        abort_if($newsletter->status === 'sent', 403);
        $newsletter->delete();

        return redirect()->route('admin.newsletters.index')
            ->with('success', __('Newsletter deleted.'));
    }

    private function renderEmailHtml(Newsletter $newsletter, array $slotArticles, string $locale, string $appUrl, string $clubName): string
    {
        $bgUrl = $newsletter->background_image
            ? $appUrl.'/storage/'.$newsletter->background_image
            : '';

        $html = '<div style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif">';

        // Header with background
        if ($bgUrl) {
            $html .= '<div style="background:url('.$bgUrl.') center/cover;padding:40px 20px;text-align:center;border-radius:8px">';
            $html .= '<h1 style="color:#d4a843;text-shadow:2px 2px 4px rgba(0,0,0,0.7);font-size:24px;margin:0">'.e($newsletter->title).'</h1>';
            $html .= '</div>';
        } else {
            $html .= '<h1 style="color:#003366;text-align:center;padding:20px">'.e($newsletter->title).'</h1>';
        }

        // Article slots in 2-column grid
        $html .= '<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px"><tr>';
        $col = 0;
        foreach ([1, 2, 3, 4] as $pos) {
            if ($col > 0 && $col % 2 === 0) {
                $html .= '</tr><tr>';
            }
            $slot = $slotArticles[$pos] ?? null;
            $html .= '<td width="50%" style="padding:8px;vertical-align:top">';
            if ($slot) {
                $article = $slot['article'];
                $t = $article->translated($locale);
                $url = $appUrl.'/article/'.$article->slug;
                $img = $article->featured_image
                    ? '<img src="'.$appUrl.'/storage/'.$article->featured_image.'" style="width:100%;max-height:120px;object-fit:cover;border-radius:4px" alt="">'
                    : '';
                $excerpt = Str::limit(strip_tags($t['body']), 120);

                $html .= '<div style="background:#fff;border:1px solid #ddd;border-radius:8px;overflow:hidden">';
                $html .= $img;
                $html .= '<div style="padding:10px">';
                $html .= '<h3 style="margin:0 0 6px;font-size:14px;color:#003366">'.e($t['title']).'</h3>';
                $html .= '<p style="margin:0 0 8px;font-size:12px;color:#555">'.$excerpt.'</p>';
                $html .= '<a href="'.$url.'" style="color:#0077be;font-size:12px;text-decoration:none">'.($locale === 'fr' ? 'Lire la suite →' : __('Read more →')).'</a>';
                $html .= '</div></div>';
            } else {
                $html .= '&nbsp;';
            }
            $html .= '</td>';
            $col++;
        }
        $html .= '</tr></table>';

        // Slot 5 — small bottom banner
        if (isset($slotArticles[5])) {
            $article = $slotArticles[5]['article'];
            $t = $article->translated($locale);
            $url = $appUrl.'/article/'.$article->slug;
            $html .= '<div style="margin-top:12px;padding:12px;background:#f0f8ff;border-radius:6px;text-align:center">';
            $html .= '<a href="'.$url.'" style="color:#003366;font-weight:bold;text-decoration:none">'.e($t['title']).'</a>';
            $html .= '</div>';
        }

        // Footer
        $html .= '<div style="margin-top:20px;padding:15px;text-align:center;font-size:11px;color:#999">';
        $html .= e($clubName).' — <a href="'.$appUrl.'" style="color:#999">'.$appUrl.'</a>';
        $html .= '</div></div>';

        return $html;
    }
}
