<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentDispatchRequest;
use App\Jobs\SendTrackedDocumentEmail;
use App\Models\DocumentDispatch;
use App\Models\DocumentDispatchRecipient;
use App\Models\LibraryFile;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class DocumentDispatchController extends Controller
{
    public function index(): View
    {
        return view('admin.document-dispatch.index', [
            'dispatches' => DocumentDispatch::with('file', 'creator.detail')
                ->withCount(['recipients', 'recipients as opened_count' => fn ($q) => $q->whereNotNull('first_opened_at')])
                ->latest()
                ->paginate(25),
        ]);
    }

    public function create(): View
    {
        $members = User::query()
            ->with([
                'detail', 'status', 'roles',
                'documents' => fn ($q) => $q->where('category', 'medical')->where('is_current', true),
                'paymentsExpected' => fn ($q) => $q->where('status', 'pending'),
            ])
            ->whereNotNull('primary_email')
            ->where('primary_email', 'not like', 'erased-%@erased.local')
            ->orderBy('username')
            ->get()
            ->sortBy(fn (User $u) => mb_strtolower($u->name))
            ->values();

        return view('admin.document-dispatch.create', [
            'files' => LibraryFile::where('mime_type', 'application/pdf')->orderBy('original_name')->get(),
            'members' => $members,
            // Same quick-select groups as the "Send Email" tool (EmailController::resolveGroup),
            // evaluated per already-loaded member instead of a separate query per group.
            'memberGroups' => $members->mapWithKeys(fn (User $m) => [$m->id => $this->groupsFor($m)]),
        ]);
    }

    /** @return list<string> */
    private function groupsFor(User $member): array
    {
        $groups = ['all'];

        if ($member->isActive()) {
            $groups[] = 'active';
        }
        if ($member->roles->contains('name', 'instructor')) {
            $groups[] = 'instructors';
        }
        if ($member->roles->pluck('name')->intersect(['bureau_master', 'bureau_finance', 'bureau_technical'])->isNotEmpty()) {
            $groups[] = 'bureau';
        }
        if ($member->documents->contains(fn ($d) => $d->expiry_date?->between(now(), now()->addDays(30)))) {
            $groups[] = 'expiring_certs';
        }
        if ($member->paymentsExpected->isNotEmpty()) {
            $groups[] = 'unpaid';
        }

        return $groups;
    }

    public function store(StoreDocumentDispatchRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $recipients = User::query()
            ->whereIn('id', $data['recipients'])
            ->whereNotNull('primary_email')
            ->get(['id', 'primary_email']);

        if ($recipients->isEmpty()) {
            return back()->withInput()->withErrors(['recipients' => __('No valid recipients.')]);
        }

        $dispatch = DocumentDispatch::create([
            'library_file_id' => $data['library_file_id'],
            'created_by' => $request->user()->id,
            'subject' => $data['subject'],
            'message' => $data['message'] ?? null,
            'recipient_summary' => trans_choice('{1}:count recipient|[2,*]:count recipients', $recipients->count(), ['count' => $recipients->count()]),
        ]);

        foreach ($recipients as $user) {
            $recipient = DocumentDispatchRecipient::create([
                'dispatch_id' => $dispatch->id,
                'user_id' => $user->id,
                'email' => $user->primary_email,
                'token' => Str::random(40),
            ]);
            SendTrackedDocumentEmail::dispatch($recipient->id)->afterCommit();
        }

        return redirect()->route('admin.document-dispatch.show', $dispatch)
            ->with('success', __('Sending to :n recipient(s).', ['n' => $recipients->count()]));
    }

    public function show(DocumentDispatch $documentDispatch): View
    {
        $documentDispatch->load(['file', 'creator.detail', 'recipients.user.detail', 'recipients.opens']);

        return view('admin.document-dispatch.show', ['dispatch' => $documentDispatch]);
    }
}
