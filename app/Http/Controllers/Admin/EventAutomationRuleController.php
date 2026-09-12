<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAutomationRule;
use App\Models\SeasonPattern;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventAutomationRuleController extends Controller
{
    public function index(): View
    {
        $rules = EventAutomationRule::with(['seasonPattern', 'event'])->latest()->get();
        $patterns = SeasonPattern::orderBy('day_of_week')->get();
        $events = Event::where('event_date', '>=', now()->subDays(7))->orderBy('event_date')->get(['id', 'title', 'event_date']);

        return view('admin.event-automation-rules.index', compact('rules', 'patterns', 'events'));
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'target' => 'required|in:pattern,event',
            'season_pattern_id' => 'required_if:target,pattern|nullable|exists:season_patterns,id',
            'event_id' => 'required_if:target,event|nullable|exists:events,id',
            'rule_type' => ['required', Rule::in([EventAutomationRule::TYPE_MIN_REGISTRATIONS, EventAutomationRule::TYPE_REQUIRES_LIFEGUARD])],
            'threshold' => 'required_if:rule_type,'.EventAutomationRule::TYPE_MIN_REGISTRATIONS.'|nullable|integer|min:0',
            'cancels_event' => 'nullable|boolean',
            'email_subject' => 'nullable|string|max:255',
            'email_body' => 'nullable|string|max:5000',
            'extra_recipients' => 'nullable|string|max:1000',
        ]);

        EventAutomationRule::create([
            'season_pattern_id' => $v['target'] === 'pattern' ? $v['season_pattern_id'] : null,
            'event_id' => $v['target'] === 'event' ? $v['event_id'] : null,
            'rule_type' => $v['rule_type'],
            'threshold' => $v['threshold'] ?? null,
            'cancels_event' => $v['cancels_event'] ?? false,
            'email_subject' => $v['email_subject'] ?? null,
            'email_body' => $v['email_body'] ?? null,
            'extra_recipients' => $v['extra_recipients'] ?? null,
        ]);

        return back()->with('success', __('Automation rule saved.'));
    }

    public function destroy(EventAutomationRule $eventAutomationRule): RedirectResponse
    {
        $eventAutomationRule->delete();

        return back()->with('success', __('Automation rule removed.'));
    }
}
