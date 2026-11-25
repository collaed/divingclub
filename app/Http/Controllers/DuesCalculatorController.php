<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CalculateDuesRequest;
use App\Models\MemberDetail;
use App\Models\MembershipFee;
use App\Models\MembershipFeeComponent;
use App\Models\MemberStatus;
use App\Models\Season;
use App\Models\StatusSet;
use App\Models\User;
use App\Services\FeeCalculationService;
use App\Services\MembershipRenewalService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DuesCalculatorController extends Controller
{
    public function __construct(private FeeCalculationService $fees, private MembershipRenewalService $renewals) {}

    /**
     * The member whose dues this request concerns: the authenticated member
     * by default, or a family member they're allowed to act for (see
     * User::canActFor()) when target_user_id is posted. Returns null for a
     * guest (not logged in) — callers fall back to resolveCalculatingUser().
     */
    private function actingUser(Request $request): ?User
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            return null;
        }

        $targetId = $request->input('target_user_id');
        if (! $targetId) {
            return $actor;
        }

        $target = User::findOrFail($targetId);
        abort_unless($actor->canActFor($target), 403);

        return $target;
    }

    public function show(Request $request): View
    {
        $year = (string) $request->input('season_year', Season::currentDuesYear());
        $user = $this->actingUser($request);
        $data = $this->viewData($year, $user);

        // Pre-check the member's own insurance choice so they don't have to
        // remember it: last season's tier when known, else the recommended one.
        if ($user instanceof User) {
            $slug = $this->renewals->preselectedInsuranceSlug($user, $year);
            $data['selectedOptionals'] = $slug ? [$slug] : [];
        }

        return view('dues-calculator', $data);
    }

    public function calculate(CalculateDuesRequest $request): View
    {
        $year = (string) $request->input('season_year', Season::currentDuesYear());
        $statusId = $request->input('status_id');
        $selectedOptionals = $this->selectedOptionals($request);

        $actingUser = $this->actingUser($request);
        $data = $this->viewData($year, $actingUser);

        $status = MemberStatus::find($statusId);
        $lastName = $actingUser?->detail?->last_name ?? strtoupper((string) $request->input('last_name', ''));
        $firstName = $actingUser?->detail?->first_name ?? strtoupper((string) $request->input('first_name', ''));

        $user = $this->resolveCalculatingUser($actingUser, $request, $lastName, $firstName);

        $calc = $this->fees->calculate($user, $year, $selectedOptionals, $status);
        $breakdown = $this->fees->breakdown($user, $year, $selectedOptionals, $status);

        return view('dues-calculator', array_merge($data, [
            'statusId' => $statusId,
            'selectedOptionals' => $selectedOptionals,
            'total' => (float) $calc['amount_due'],
            'communication' => $calc['communication'],
            'breakdown' => $breakdown,
            'components' => $calc['components'],
            'derivedFfessm' => $calc['components']['ffessm_licence'] ?? null,
            'flassaState' => $calc['components']['flassa_state'] ?? null,
            'lastName' => $lastName,
            'firstName' => $firstName,
        ]));
    }

    /**
     * The user whose age drives the derivation: the resolved acting user
     * (self or family member), or a transient guest built from the posted
     * name and date of birth so the service can still derive the
     * FFESSM/FLASSA block.
     */
    private function resolveCalculatingUser(?User $actingUser, CalculateDuesRequest $request, string $lastName, string $firstName): User
    {
        if ($actingUser instanceof User) {
            return $actingUser;
        }

        $guest = new User;
        $guest->id = 0;
        $dob = $request->input('date_of_birth');
        $detail = new MemberDetail([
            'last_name' => $lastName,
            'first_name' => $firstName,
            'date_of_birth' => is_string($dob) && $dob !== '' ? $dob : null,
        ]);
        $guest->setRelation('detail', $detail);

        return $guest;
    }

    /**
     * A logged-in member commits to a computed dues figure. The commitment is
     * written to payment_expected for later (AI-assisted) reconciliation. It
     * NEVER changes the member's profile status/set — an unclassified member's
     * commitment is flagged provisional for bureau review.
     */
    public function commit(Request $request): RedirectResponse
    {
        $actor = auth()->user();
        abort_unless($actor !== null, 403);

        $v = $request->validate([
            'season_year' => 'required|string|max:10',
            'status_id' => 'required|integer|exists:member_statuses,id',
            'target_user_id' => 'nullable|integer|exists:users,id',
        ]);

        $user = $actor;
        if (! empty($v['target_user_id'])) {
            $user = User::findOrFail($v['target_user_id']);
            abort_unless($actor->canActFor($user), 403);
        }
        $selectedOptionals = $this->selectedOptionals($request);

        $status = MemberStatus::find($v['status_id']);

        // "Ancien membre" (former) and the system "Actif" status can never be
        // self-claimed via the calculator.
        if ($status !== null && in_array($status->slug, MemberStatus::unselectableSlugs(), true)) {
            return back()->withErrors(['status_id' => __('This status cannot be selected.')]);
        }

        // Provisional when the member has no assigned status set, or the chosen
        // status is not (yet) their profile status.
        $provisional = $user->status_set_id === null || (int) $user->status_id !== (int) $v['status_id'];

        $pe = $this->fees->createPaymentExpected($user, $v['season_year'], $selectedOptionals, $provisional, $status);

        $msg = $provisional
            ? __('Your commitment was recorded for review. Amount: €:amount', ['amount' => number_format((float) $pe->amount_due, 2)])
            : __('Your dues were recorded. Amount: €:amount', ['amount' => number_format((float) $pe->amount_due, 2)]);

        return redirect()->route('dues.show', ['season_year' => $v['season_year']])->with('success', $msg);
    }

    /**
     * Shared view data: the statuses offered to the current viewer (constrained
     * to their status set when classified) plus fees and optional components.
     *
     * @return array<string, mixed>
     */
    private function viewData(string $year, ?User $user): array
    {
        $set = $user?->statusSet;

        if ($set instanceof StatusSet) {
            $statuses = $set->statuses()->orderBy('name')->get();
        } else {
            $statuses = MemberStatus::orderBy('name')->get();
        }

        // "Ancien membre" (former) is a lifecycle status assigned by the bureau
        // when someone leaves, and "Actif" is a system default — neither can be
        // self-selected on the calculator.
        $statuses = $statuses->reject(
            fn (MemberStatus $s): bool => in_array($s->slug, MemberStatus::unselectableSlugs(), true)
        )->values();

        $unclassified = $user !== null && ! $set instanceof StatusSet;

        $fees = $this->feesForYear($year);
        // Only assurance components are user-selectable; FFESSM + FLASSA are derived.
        $assurances = MembershipFeeComponent::where('is_optional', true)
            ->where('kind', MembershipFeeComponent::KIND_ASSURANCE)
            ->orderBy('sort_order')->get();
        $ffessmLicences = MembershipFeeComponent::where('kind', MembershipFeeComponent::KIND_FFESSM_LICENCE)
            ->orderBy('sort_order')->get()->keyBy('slug');
        $taperPct = $this->fees->taperPercentage($year);
        $season = $this->fees->resolveSeason($year);
        $minorPercent = $season?->minor_fee_percent ?? Season::MINOR_FEE_DEFAULT_PERCENT;

        return [
            'minorRule' => $minorPercent < 100
                ? ['age' => $season?->minor_fee_below_age ?? Season::MINOR_FEE_DEFAULT_AGE, 'percent' => $minorPercent]
                : null,
            'year' => $year,
            'statuses' => $statuses,
            'fees' => $fees,
            'optionals' => $assurances,
            'ffessmLicences' => $ffessmLicences,
            'taperPct' => $taperPct,
            'unclassified' => $unclassified,
            'isGuest' => $user === null,
            'memberDob' => $user?->detail?->date_of_birth?->toDateString(),
            'actingUserId' => $user?->id,
            'actingLastName' => $user?->detail?->last_name,
            'actingFirstName' => $user?->detail?->first_name,
            'familyMembers' => auth()->user()?->familyMembers() ?? new \Illuminate\Database\Eloquent\Collection,
        ];
    }

    /**
     * Fees for the requested year, falling back to the most recent prior year
     * that has fees defined ("last good version"). This means the bureau does
     * not need to duplicate unchanged fee records every season.
     *
     * @return Collection<int, MembershipFee>
     */
    private function feesForYear(string $year): Collection
    {
        $fees = MembershipFee::where('season_year', $year)->with('status')->get();
        if ($fees->isEmpty()) {
            $fallbackYear = MembershipFee::where('season_year', '<', $year)
                ->orderByDesc('season_year')
                ->value('season_year');

            $fees = $fallbackYear === null
                ? collect()
                : MembershipFee::where('season_year', $fallbackYear)->with('status')->get();
        }

        $byStatusId = $fees->keyBy('status_id');

        // Fee-alias statuses (Famille/Associé/Assimilé) show their parent
        // status's fee when they have none of their own for this year — same
        // rule FeeCalculationService applies when actually charging a member.
        $aliasStatuses = MemberStatus::whereIn('slug', array_keys(MemberStatus::FEE_ALIASES))->get();
        foreach ($aliasStatuses as $status) {
            if ($byStatusId->has($status->id)) {
                continue;
            }
            $aliasFee = $byStatusId->first(fn (MembershipFee $f): bool => $f->status?->slug === $status->feeAliasSlug());
            if ($aliasFee) {
                $byStatusId->put($status->id, $aliasFee);
            }
        }

        return $byStatusId;
    }

    /**
     * Collect selected optional slugs from both the checkbox array and any
     * radio-group inputs (optionals_{group}).
     *
     * @return array<int, string>
     */
    private function selectedOptionals(Request $request): array
    {
        $selected = (array) $request->input('optionals', []);
        foreach ($request->all() as $key => $val) {
            if (is_string($key) && str_starts_with($key, 'optionals_') && $val) {
                $selected[] = $val;
            }
        }

        return array_values(array_filter(array_map('strval', $selected)));
    }
}
