<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Article;
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

    public function index(Request $request): View
    {
        $kind = array_key_exists((string) $request->get('kind'), self::KINDS) ? (string) $request->get('kind') : 'events';
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

        $counts = [];
        foreach (self::KINDS as $k => $s) {
            $counts[$k] = $s['class']::onlyTrashed()->count();
        }

        return view('admin.trash.index', [
            'kinds' => self::KINDS,
            'kind' => $kind,
            'spec' => $spec,
            'rows' => $rows,
            'actors' => $actors,
            'counts' => $counts,
        ]);
    }

    public function restore(string $kind, int $id): RedirectResponse
    {
        $model = $this->find($kind, $id);
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
