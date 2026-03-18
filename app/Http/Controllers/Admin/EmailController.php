<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EmailController extends Controller
{
    public function index()
    {
        $templates = EmailTemplate::orderBy('name')->get();
        $log = EmailLog::orderByDesc('created_at')->paginate(30);
        return view('admin.email.index', compact('templates', 'log'));
    }

    public function storeTemplate(Request $request)
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

    public function updateTemplate(Request $request, EmailTemplate $template)
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);
        $template->update($v);
        return back()->with('success', __('Template updated.'));
    }

    public function destroyTemplate(EmailTemplate $template)
    {
        $template->delete();
        return back()->with('success', __('Template deleted.'));
    }

    public function preview(Request $request)
    {
        $template = EmailTemplate::findOrFail($request->template_id);
        $user = User::with('detail')->first();
        $rendered = $this->renderTemplate($template, $user);
        return response()->json($rendered);
    }

    public function send(Request $request)
    {
        $request->validate([
            'template_id' => 'required|exists:email_templates,id',
            'group' => 'required|in:all,active,instructors,bureau,expiring_certs,unpaid',
        ]);

        $template = EmailTemplate::findOrFail($request->template_id);
        $users = $this->resolveGroup($request->group);

        $sent = 0;
        foreach ($users as $user) {
            $rendered = $this->renderTemplate($template, $user);
            // Queue email
            EmailLog::create([
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
        dispatch(function () {
            $queued = EmailLog::where('status', 'queued')->get();
            foreach ($queued as $log) {
                try {
                    Mail::raw($log->body, fn($m) => $m->to($log->to_email)->subject($log->subject));
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
        $vars = [
            '{{first_name}}' => $user->detail?->first_name ?? '',
            '{{last_name}}' => $user->detail?->last_name ?? '',
            '{{name}}' => $user->name,
            '{{email}}' => $user->primary_email,
            '{{club_name}}' => \App\Models\ThemeSetting::get('club_full_name', 'Diving Club'),
        ];
        return [
            'subject' => str_replace(array_keys($vars), array_values($vars), $template->subject),
            'body' => str_replace(array_keys($vars), array_values($vars), $template->body),
        ];
    }

    private function resolveGroup(string $group)
    {
        return match ($group) {
            'all' => User::with('detail')->whereNotNull('email_verified_at')->get(),
            'active' => User::with('detail')->whereHas('status', fn($q) => $q->where('slug', 'actif'))->get(),
            'instructors' => User::with('detail')->whereHas('role', fn($q) => $q->where('slug', 'instructor'))->get(),
            'bureau' => User::with('detail')->whereHas('role', fn($q) => $q->whereIn('slug', ['bureau_master', 'bureau_finance', 'bureau_technical']))->get(),
            'expiring_certs' => User::with('detail')->whereHas('documents', fn($q) => $q->where('category', 'medical')->where('is_current', true)->whereBetween('expiry_date', [now(), now()->addDays(30)]))->get(),
            'unpaid' => User::with('detail')->whereHas('paymentsExpected', fn($q) => $q->where('status', 'pending'))->get(),
            default => collect(),
        };
    }
}
