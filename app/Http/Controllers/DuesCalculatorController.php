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
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DuesCalculatorController extends Controller
{
    public function __construct(private FeeCalculationService $fees) {}

    public function show(Request $request): View
    {
        $year = (string) $request->input('season_year', Season::currentDuesYear());

        return view('dues-calculator', $this->viewData($year));
    }

    public function calculate(CalculateDuesRequest $request): View
    {
        $year = (string) $request->input('season_year', Season::currentDuesYear());
        $statusId = $request->input('status_id');
        $selectedOptionals = $this->selectedOptionals($request);

        $data = $this->viewData($year);

        $status = MemberStatus::find($statusId);
        $lastName = strtoupper((string) $request->input('last_name', ''));
        $firstName = strtoupper((string) $request->input('first_name', ''));

        $user = $this->resolveCalculatingUser($request, $lastName, $firstName);

        $calc = $this->fees->calculate($user, $year, $selectedOptionals, $status);
        $breakdown = $this->fees->breakdown($user, $year, $selectedOptionals);

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
     * The user whose age drives the derivation: the authenticated member, or a
     * transient guest built from the posted name and date of birth so the
     * service can still derive the FFESSM/FLASSA block.
     */
    private function resolveCalculatingUser(CalculateDuesRequest $request, string $lastName, string $firstName): User
    {
        $user = auth()->user();
        if ($user instanceof User) {
            return $user;
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
        $user = auth()->user();
        abort_unless($user !== null, 403);

        $v = $request->validate([
            'season_year' => 'required|string|max:10',
            'status_id' => 'required|integer|exists:member_statuses,id',
        ]);
        $selectedOptionals = $this->selectedOptionals($request);

        $status = MemberStatus::find($v['status_id']);

        // "Ancien membre" (former) can never be self-claimed via the calculator.
        if ($status !== null && in_array($status->slug, MemberStatus::inactiveSlugs(), true)) {
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
    private function viewData(string $year): array
    {
        $user = auth()->user();
        $set = $user?->statusSet;

        if ($set instanceof StatusSet) {
            $statuses = $set->statuses()->orderBy('name')->get();
        } else {
            $statuses = MemberStatus::orderBy('name')->get();
        }

        // "Ancien membre" (former) is a lifecycle status assigned by the bureau
        // when someone leaves; it can never be self-selected on the calculator.
        $statuses = $statuses->reject(
            fn (MemberStatus $s): bool => in_array($s->slug, MemberStatus::inactiveSlugs(), true)
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

        return [
            'year' => $year,
            'statuses' => $statuses,
            'fees' => $fees,
            'optionals' => $assurances,
            'ffessmLicences' => $ffessmLicences,
            'taperPct' => $taperPct,
            'unclassified' => $unclassified,
            'isGuest' => $user === null,
            'memberDob' => $user?->detail?->date_of_birth?->toDateString(),
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
        if ($fees->isNotEmpty()) {
            return $fees->keyBy('status_id');
        }

        $fallbackYear = MembershipFee::where('season_year', '<', $year)
            ->orderByDesc('season_year')
            ->value('season_year');

        if ($fallbackYear === null) {
            return collect();
        }

        return MembershipFee::where('season_year', $fallbackYear)->with('status')->get()->keyBy('status_id');
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
