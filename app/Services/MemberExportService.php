<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MemberLicence;
use App\Models\User;
use Illuminate\Support\Carbon;

class MemberExportService
{
    /**
     * Build a fully denormalized member dataset: one flat row per member with
     * every related field expanded into its own column, so the result can be
     * filtered, pivoted and charted directly in a spreadsheet.
     *
     * @return array{headers: list<string>, rows: list<list<string|int|float|null>>}
     */
    public function build(): array
    {
        $members = User::query()
            ->with(['detail', 'roles', 'status', 'statusSet', 'licences.federation', 'certificationLevels'])
            ->orderBy('id')
            ->get();

        $federationAcronyms = $this->federationAcronyms();

        $headers = array_merge($this->baseHeaders(), $this->federationHeaders($federationAcronyms));

        $rows = $members->map(fn (User $member): array => $this->row($member, $federationAcronyms))->all();

        return ['headers' => $headers, 'rows' => $rows];
    }

    /** @return list<string> */
    protected function baseHeaders(): array
    {
        return [
            'ID', 'Username', 'Email', 'Club Email', 'Status', 'Status Set', 'Roles', 'Email Verified', 'Locale',
            'First Name', 'Last Name', 'Birth Name', 'Sex', 'Date of Birth', 'Age', 'Place of Birth', 'Nationality',
            'Phone Private', 'Phone Office', 'Phone Mobile',
            'Address Line 1', 'Address Line 2', 'Postal Code', 'City', 'Country', 'IBAN',
            'Emergency Contact Name', 'Emergency Contact Phone', 'Emergency Contact Relationship',
            'Adhesion Year', 'Cotisation Years', 'Seasons Paid', 'Paid Current Season', 'Bureau Member', 'Active Instructor',
            'Certification Level', 'Apnea Level', 'Other Certifications', 'All Certifications',
            'Brevet Date', 'Dive Count', 'Total Dives', 'Last Dive Date', 'Air Consumption', 'Ease Level', 'Primary Intent', 'Photographer',
            'BCD Size', 'T-Shirt Size', 'Suit Brand', 'Suit Size',
            'Registered At',
        ];
    }

    /**
     * Two columns per federation the club actually uses (licence number + medical
     * certificate expiry), so licences — a one-to-many relation — become flat,
     * filterable columns rather than a nested list.
     *
     * @param  list<string>  $acronyms
     * @return list<string>
     */
    protected function federationHeaders(array $acronyms): array
    {
        $headers = [];
        foreach ($acronyms as $acronym) {
            $headers[] = "{$acronym} Licence No";
            $headers[] = "{$acronym} Medical Expiry";
        }

        return $headers;
    }

    /**
     * @param  list<string>  $federationAcronyms
     * @return list<string|int|float|null>
     */
    protected function row(User $member, array $federationAcronyms): array
    {
        $d = $member->detail;

        $row = [
            $member->id,
            $member->username,
            $member->primary_email,
            $d?->club_email,
            $member->status?->name,
            $member->statusSet?->name,
            $member->roles->pluck('name')->implode(', '),
            $member->email_verified_at ? 'Yes' : 'No',
            $member->preferred_locale,
            $d?->first_name,
            $d?->last_name,
            $d?->birth_name,
            $d?->sex,
            $this->date($d?->date_of_birth),
            $d?->date_of_birth ? $d->date_of_birth->age : null,
            $d?->place_of_birth,
            $d?->nationality,
            $d?->phone_private,
            $d?->phone_office,
            $d?->phone_mobile,
            $d?->address_line1,
            $d?->address_line2,
            $d?->postal_code,
            $d?->city,
            $d?->country,
            $d?->iban,
            $d?->emergency_contact_name,
            $d?->emergency_contact_phone,
            $d?->emergency_contact_relationship,
            $d?->adhesion_year,
            $this->list($d?->cotisation_years),
            is_array($d?->cotisation_years) ? count($d->cotisation_years) : 0,
            $this->paidCurrentSeason($d?->cotisation_years) ? 'Yes' : 'No',
            $this->bool($d?->bureau_member),
            $this->bool($d?->active_instructor),
            $d?->certification_level,
            $d?->apnea_level,
            $this->list($d?->other_certifications),
            $member->certificationLevels->pluck('name')->implode(', '),
            $this->date($d?->brevet_date),
            $d?->dive_count,
            $d?->total_dives,
            $this->date($d?->last_dive_date),
            $d?->air_consumption,
            $d?->ease_level,
            $d?->primary_intent,
            $this->bool($d?->is_photographer),
            $d?->bcd_size,
            $d?->tshirt_size,
            $d?->suit_brand,
            $d?->suit_size,
            $this->date($member->created_at),
        ];

        $licencesByAcronym = $member->licences->keyBy(fn (MemberLicence $licence): string => (string) $licence->federation?->acronym);
        foreach ($federationAcronyms as $acronym) {
            $licence = $licencesByAcronym->get($acronym);
            $row[] = $licence?->licence_number;
            $row[] = $this->date($licence?->medical_cert_expiry);
        }

        return $row;
    }

    /** Distinct federation acronyms across all licences, alphabetically. @return list<string> */
    protected function federationAcronyms(): array
    {
        return MemberLicence::query()
            ->with('federation')
            ->get()
            ->map(fn (MemberLicence $licence): ?string => $licence->federation?->acronym)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /** @param mixed $value */
    protected function bool($value): string
    {
        return $value ? 'Yes' : 'No';
    }

    protected function date(?Carbon $value): ?string
    {
        return $value?->format('Y-m-d');
    }

    /** @param mixed $value */
    protected function list($value): ?string
    {
        return is_array($value) ? implode(', ', $value) : null;
    }

    /**
     * The club season rolls over in September, matching User::isActive().
     *
     * @param  mixed  $cotisationYears
     */
    protected function paidCurrentSeason($cotisationYears): bool
    {
        if (! is_array($cotisationYears)) {
            return false;
        }

        $currentSeason = (string) (now()->month >= 9 ? now()->year + 1 : now()->year);

        return in_array($currentSeason, array_map('strval', $cotisationYears), true);
    }
}
