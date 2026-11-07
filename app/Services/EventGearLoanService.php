<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendGearLoanedEmail;
use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\EventRegistration;
use App\Models\EventWeightLoan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lends tank/regulator/BCD/weights for one event registration, from the
 * bureau's quick-entry shorthand on the participant row. Resolves the
 * whole string before applying anything — one bad code in the batch means
 * nothing is applied, so the bureau can fix it and resubmit.
 */
final class EventGearLoanService
{
    /** Shorthand letter => Equipment::type, for a specific numbered item. */
    private const ITEM_TYPES = ['t' => 'tank', 'r' => 'regulator', 'j' => 'bcd'];

    /** Shorthand letter => quantity kind (weights aren't numbered Equipment rows). */
    private const QUANTITY_TYPES = ['w' => 'weight'];

    /** BCD size prefixes a code may optionally start with (longest first, so "xxs" isn't cut short at "xs"). */
    private const BCD_SIZES = ['xxxs', 'xxs', 'xxl', 'xs', 'xl', 's', 'm', 'l'];

    public function __construct(private GearShorthandParser $parser) {}

    /**
     * @return array{ok: bool, items: list<array{type: string, equipment: Equipment}>, weightQuantity: int|null, errors: list<string>}
     */
    public function preview(string $text, EventRegistration $registration): array
    {
        $items = [];
        $claimedIds = [];
        $weightQuantity = null;
        $errors = [];

        foreach ($this->parser->tokenize($text) as $token) {
            if ($token['kind'] === 'item' && isset(self::ITEM_TYPES[$token['letter']])) {
                $type = self::ITEM_TYPES[$token['letter']];
                $code = $token['code'];
                $size = null;
                if ($type === 'bcd') {
                    [$size, $code] = $this->splitBcdSize($code);
                }
                $equipment = $this->findAvailableItem($type, $code, $size, $claimedIds);
                if ($equipment === null) {
                    $errors[] = __(':code: no available :type matching :value', ['code' => $token['raw'], 'type' => $type, 'value' => $token['code']]);
                } else {
                    $items[] = ['type' => $type, 'equipment' => $equipment];
                    $claimedIds[] = $equipment->id;
                }
            } elseif ($token['kind'] === 'quantity' && isset(self::QUANTITY_TYPES[$token['letter']])) {
                $weightQuantity = ($weightQuantity ?? 0) + $token['number'];
            } else {
                $errors[] = __(':code: not recognised', ['code' => $token['raw']]);
            }
        }

        return ['ok' => $errors === [], 'items' => $items, 'weightQuantity' => $weightQuantity, 'errors' => $errors];
    }

    private function findAvailableItem(string $type, string $code, ?string $size, array $excludeIds): ?Equipment
    {
        return Equipment::where('type', $type)
            ->where('is_loanable', true)
            ->when($size !== null, fn ($q) => $q->whereRaw('LOWER(size) = ?', [$size]))
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->get()
            ->first(fn (Equipment $e): bool => $this->codeMatches($e->short_number, $code) && $e->isAvailable());
    }

    private function codeMatches(?string $shortNumber, string $code): bool
    {
        return $shortNumber !== null && strcasecmp(trim($shortNumber), $code) === 0;
    }

    /**
     * Splits an optional leading size prefix off a BCD code, e.g. "xs36" => ['xs', '36'].
     * Without a recognised size prefix, the code is returned unchanged and any available
     * BCD matching the number is accepted regardless of its size.
     *
     * @return array{0: string|null, 1: string}
     */
    private function splitBcdSize(string $code): array
    {
        foreach (self::BCD_SIZES as $size) {
            if (str_starts_with($code, $size) && $code !== $size && ctype_digit(substr($code, strlen($size)))) {
                return [$size, substr($code, strlen($size))];
            }
        }

        return [null, $code];
    }

    /**
     * @param  array{ok: bool, items: list<array{type: string, equipment: Equipment}>, weightQuantity: int|null, errors: list<string>}  $preview
     * @return array{items: list<array{short_number: string|null, name: string}>, weightQuantity: int|null}
     */
    public function apply(EventRegistration $registration, array $preview): array
    {
        return DB::transaction(function () use ($registration, $preview): array {
            $loaned = [];

            foreach ($preview['items'] as $pick) {
                /** @var Equipment $equipment */
                $equipment = $pick['equipment'];

                EquipmentLoan::create([
                    'equipment_id' => $equipment->id,
                    'user_id' => $registration->user_id,
                    'event_registration_id' => $registration->id,
                    'loaned_at' => now(),
                    'loaned_by' => auth()->id(),
                ]);
                $equipment->update(['status' => 'on_loan', 'last_seen_at' => now()]);

                $loaned[] = ['short_number' => $equipment->short_number, 'name' => $equipment->name];
            }

            if ($preview['weightQuantity'] !== null) {
                EventWeightLoan::create([
                    'event_id' => $registration->event_id,
                    'event_registration_id' => $registration->id,
                    'quantity' => $preview['weightQuantity'],
                    'loaned_by' => auth()->id(),
                    'loaned_at' => now(),
                ]);
            }

            if ($loaned !== [] || $preview['weightQuantity'] !== null) {
                SendGearLoanedEmail::dispatch($registration->user_id, $loaned, $preview['weightQuantity']);
            }

            return $this->currentGear($registration);
        });
    }

    /** @return array{items: list<array{short_number: string|null, name: string}>, weightQuantity: int|null} */
    public function currentGear(EventRegistration $registration): array
    {
        /** @var Collection<int, EquipmentLoan> $loans */
        $loans = $registration->equipmentLoans()->whereNull('returned_at')->with('equipment')->get();
        $items = $loans
            ->map(fn (EquipmentLoan $loan): array => ['short_number' => $loan->equipment->short_number, 'name' => $loan->equipment->name])
            ->values()
            ->all();

        /** @var Collection<int, EventWeightLoan> $weightLoans */
        $weightLoans = $registration->weightLoans()->get();
        $weightQuantity = $weightLoans->sum(fn (EventWeightLoan $l): int => $l->outstanding());

        return ['items' => $items, 'weightQuantity' => $weightQuantity > 0 ? $weightQuantity : null];
    }
}
