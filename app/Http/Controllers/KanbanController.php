<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\KanbanCard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A kanban board of action items extracted from compte-rendus, visible to
 * bureau and instructors (see the route group in routes/web.php). Cards are
 * created by the compte-rendus:extract-actions command, or — on the app
 * that only sources the documents — pushed here by KanbanIngestController.
 */
class KanbanController extends Controller
{
    public function index(): View
    {
        $cards = KanbanCard::active()->with('comments.user.detail')->orderByDesc('source_document_date')->get()->groupBy('status');

        return view('kanban.index', [
            'columns' => [
                KanbanCard::STATUS_TODO => __('To do'),
                KanbanCard::STATUS_DOING => __('In progress'),
                KanbanCard::STATUS_DONE => __('Done'),
            ],
            'cards' => $cards,
        ]);
    }

    /** A card added by hand from the board — no source document, always starts in "To do". */
    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'title' => 'required|string|max:255',
            'responsible' => 'nullable|string|max:255',
            'context' => 'nullable|string|max:2000',
        ]);

        KanbanCard::create($v + ['status' => KanbanCard::STATUS_TODO]);

        return back()->with('success', __('Card added.'));
    }

    public function updateStatus(Request $request, KanbanCard $card): RedirectResponse|JsonResponse
    {
        $v = $request->validate(['status' => 'required|in:'.implode(',', KanbanCard::STATUSES)]);
        $card->update(['status' => $v['status']]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    public function discard(Request $request, KanbanCard $card): RedirectResponse|JsonResponse
    {
        $card->update(['discarded_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', __('Card discarded.'));
    }

    /** One line in the card's progress log — who and when read from the author, not typed. */
    public function storeComment(Request $request, KanbanCard $card): RedirectResponse
    {
        $v = $request->validate(['body' => 'required|string|max:2000']);

        $card->comments()->create(['user_id' => $request->user()->id, 'body' => $v['body']]);

        return back()->with('success', __('Comment added.'));
    }
}
