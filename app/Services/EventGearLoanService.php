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
                $equipment = $this->findAvailableItem($type, $token['number'], $claimedIds);
                if ($equipment === null) {
                    $errors[] = __(':code: no available :type numbered :number', ['code' => $token['raw'], 'type' => $type, 'number' => $token['number']]);
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

    private function findAvailableItem(string $type, int $number, array $excludeIds): ?Equipment
    {
        return Equipment::where('type', $type)
            ->where('is_loanable', true)
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->get()
            ->first(fn (Equipment $e): bool => $this->numberMatches($e->short_number, $number) && $e->isAvailable());
    }

    private function numberMatches(?string $shortNumber, int $number): bool
    {
        if ($shortNumber === null) {
            return false;
        }

        $digits = preg_replace('/\D/', '', $shortNumber);

        return $digits !== '' && (int) $digits === $number;
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
