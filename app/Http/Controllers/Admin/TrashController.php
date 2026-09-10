<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Event;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Recycle bin for soft-deleted records. Lets the bureau find and restore
 * accidental deletions; permanent removal is bureau_master only.
 */
class TrashController extends Controller
{
    use PaginatesFromRequest;

    /** @var array<string, array{class: class-string<Model>, label: string, icon: string, name: string}> */
    private const KINDS = [
        'events' => ['class' => Event::class, 'label' => 'Events', 'icon' => '📅', 'name' => 'title'],
        'articles' => ['class' => Article::class, 'label' => 'Articles', 'icon' => '📝', 'name' => 'title'],
        'documents' => ['class' => Document::class, 'label' => 'Documents', 'icon' => '📁', 'name' => 'original_filename'],
        'equipment' => ['class' => Equipment::class, 'label' => 'Equipment', 'icon' => '🤿', 'name' => 'name'],
        'votes' => ['class' => Vote::class, 'label' => 'Votes', 'icon' => '🗳️', 'name' => 'title'],
        'members' => ['class' => User::class, 'label' => 'Members', 'icon' => '👥', 'name' => 'username'],
    ];

    /** Pseudo-kind: cancelled (not deleted) events, so the bureau can un-cancel or bin them from one place. */
    private const CANCELLED_EVENTS = 'cancelled-events';

    public function index(Request $request): View
    {
        $requested = (string) $request->get('kind');
        $cancelledMode = $requested === self::CANCELLED_EVENTS;
        $kind = $cancelledMode ? self::CANCELLED_EVENTS
            : (array_key_exists($requested, self::KINDS) ? $requested : 'events');

        $counts = [];
        foreach (self::KINDS as $k => $s) {
            $counts[$k] = $s['class']::onlyTrashed()->count();
        }
        $counts[self::CANCELLED_EVENTS] = Event::query()->where('status', 'cancelled')->count();

        if ($cancelledMode) {
            $rows = Event::query()->where('status', 'cancelled')
                ->orderByDesc('event_date')
                ->paginate($this->perPage(25))->withQueryString();

            return view('admin.trash.index', [
                'kinds' => self::KINDS,
                'kind' => $kind,
                'spec' => ['label' => 'Cancelled events', 'icon' => '🚫', 'name' => 'title'],
                'rows' => $rows,
                'actors' => collect(),
                'counts' => $counts,
                'cancelledMode' => true,
            ]);
        }

        $spec = self::KINDS[$kind];
        $query = $spec['class']::onlyTrashed()->orderByDesc('deleted_at');

        // Never surface GDPR-erased members as "restorable".
        if ($kind === 'members') {
            $query->where('primary_email', 'not like', 'erased-%@erased.local');
        }

        $rows = $query->paginate($this->perPage(25))->withQueryString();

        // Who deleted each row — from the audit trail.
        $actors = AuditLog::where('model_type', $spec['class'])
            ->where('action', 'deleted')
            ->whereIn('model_id', $rows->pluck('id'))
            ->with('user.detail')
            ->get()
            ->keyBy('model_id');

        return view('admin.trash.index', [
            'kinds' => self::KINDS,
            'kind' => $kind,
            'spec' => $spec,
            'rows' => $rows,
            'actors' => $actors,
            'counts' => $counts,
            'cancelledMode' => false,
        ]);
    }

    /** Un-cancel a cancelled event (back to scheduled). */
    public function restoreCancelledEvent(Event $event): RedirectResponse
    {
        abort_unless($event->status === 'cancelled', 400);
        $event->update(['status' => 'scheduled', 'inscriptions_closed' => false]);

        return back()->with('success', __(':name restored.', ['name' => $event->title]));
    }

    /** Move a cancelled event to the recycle bin proper (soft delete). */
    public function trashCancelledEvent(Event $event): RedirectResponse
    {
        abort_unless($event->status === 'cancelled', 400);
        $event->delete();

        return back()->with('success', __(':name moved to the recycle bin.', ['name' => $event->title]));
    }

    public function restore(string $kind, int $id): RedirectResponse
    {
        $model = $this->find($kind, $id);
        /** @phpstan-ignore-next-line — every KINDS model uses SoftDeletes; find() is typed as the base Model */
        $model->restore();

        // A member's 1:1 detail row is soft-deleted alongside the user.
        if ($model instanceof User) {
            $model->detail()->onlyTrashed()->first()?->restore();
        }

        return back()->with('success', __(':name restored.', ['name' => $this->displayName($kind, $model)]));
    }

    public function forceDelete(string $kind, int $id): RedirectResponse
    {
        abort_unless(auth()->user()?->hasRole('bureau_master'), 403);

        $this->find($kind, $id)->forceDelete();

        return back()->with('success', __('Permanently deleted.'));
    }

    private function find(string $kind, int $id): Model
    {
        abort_unless(array_key_exists($kind, self::KINDS), 404);

        return self::KINDS[$kind]['class']::onlyTrashed()->findOrFail($id);
    }

    private function displayName(string $kind, Model $model): string
    {
        $field = self::KINDS[$kind]['name'];

        return (string) ($model->{$field} ?: '#'.$model->getKey());
    }
}
