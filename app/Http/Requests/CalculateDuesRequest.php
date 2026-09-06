<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\MembershipFeeComponent;
use App\Models\MemberStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Validates a dues calculation request and enforces age-vs-status eligibility
 * "à la prise de licence" (requirements R-N3, R1-caveat): if the member's date
 * of birth is known and the chosen cotisation band contradicts their age at the
 * season anchor, a clear inline error is surfaced rather than silently
 * mispricing the licence.
 */
class CalculateDuesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'season_year' => 'required|string|max:10',
            'status_id' => 'required|integer|exists:member_statuses,id',
            'last_name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'optionals' => 'array',
            'optionals.*' => 'string',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $status = MemberStatus::find($this->input('status_id'));
            if ($status === null) {
                return;
            }

            $dob = $this->resolveDateOfBirth();
            if (! $dob instanceof Carbon) {
                return; // Unknown DOB — cannot check, treated as adult downstream.
            }

            $anchor = $this->seasonAnchor();
            $age = (int) $dob->diffInYears($anchor);
            $expected = $this->expectedBandForStatus($status->slug);

            if ($expected !== null && ! $this->ageMatchesBand($age, $expected)) {
                $validator->errors()->add(
                    'status_id',
                    __('The selected membership does not match the member age at the season start.')
                );
            }
        });
    }

    private function resolveDateOfBirth(): ?Carbon
    {
        $posted = $this->input('date_of_birth');
        if (is_string($posted) && $posted !== '') {
            return Carbon::parse($posted);
        }

        $dob = $this->user()?->detail?->date_of_birth;

        return $dob instanceof Carbon ? $dob : null;
    }

    private function seasonAnchor(): Carbon
    {
        $flassa = MembershipFeeComponent::where('kind', MembershipFeeComponent::KIND_FLASSA)->first();
        if ($flassa?->age_anchor_date instanceof Carbon) {
            return $flassa->age_anchor_date;
        }

        $year = (int) $this->input('season_year', (string) Carbon::today()->year);

        // Season "2027" starts Sept of the prior calendar year by convention.
        return Carbon::createFromDate($year - 1, 9, 1);
    }

    /** The age band a status implies, or null when age-agnostic. */
    private function expectedBandForStatus(string $slug): ?string
    {
        return match ($slug) {
            'enfant' => 'enfant',
            'junior' => 'young', // enfant or jeune or adulte (<18 handled by FLASSA)
            'fonctionnaire', 'externe', 'actif' => 'adulte',
            default => null, // sympathisant, honoraire, etc. — no age gate
        };
    }

    private function ageMatchesBand(int $age, string $expected): bool
    {
        return match ($expected) {
            'enfant' => $age < 12,
            'adulte' => $age >= 18,
            'young' => $age < 18,
            default => true,
        };
    }
}
