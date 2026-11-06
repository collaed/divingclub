<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendGearReturnedEmail;
use App\Models\Equipment;
use App\Models\EquipmentLoan;
use App\Models\Event;
use App\Models\EventWeightLoan;
use Illuminate\Support\Facades\DB;

/**
 * Returns tank/regulator/BCD/weights from the Equipment admin page's
 * quick-entry shorthand. Resolves the whole string before applying
 * anything — one bad code means nothing is applied.
 */
final class EquipmentReturnService
{
    private const ITEM_TYPES = ['t' => 'tank', 'r' => 'regulator', 'j' => 'bcd'];

    private const QUANTITY_TYPES = ['w' => 'weight'];

    public function __construct(private GearShorthandParser $parser) {}

    /**
     * @return array{ok: bool, loans: list<EquipmentLoan>, weightReturns: list<array{event: Event, quantity: int}>, errors: list<string>}
     */
    public function preview(string $text): array
    {
        $loans = [];
        $claimedLoanIds = [];
        $weightReturns = [];
        $errors = [];

        foreach ($this->parser->tokenize($text) as $token) {
            if ($token['kind'] === 'item' && isset(self::ITEM_TYPES[$token['letter']])) {
                $type = self::ITEM_TYPES[$token['letter']];
                $loan = $this->findOpenLoan($type, $token['number'], $claimedLoanIds);
                if ($loan === null) {
                    $errors[] = __(':code: no :type numbered :number is currently on loan', ['code' => $token['raw'], 'type' => $type, 'number' => $token['number']]);
                } else {
                    $loans[] = $loan;
                    $claimedLoanIds[] = $loan->id;
                }
            } elseif ($token['kind'] === 'quantity' && isset(self::QUANTITY_TYPES[$token['letter']])) {
                $resolved = $this->resolveWeightReturn($token['number']);
                if ($resolved === null) {
                    $errors[] = __(':code: could not be matched to a single event\'s outstanding weights', ['code' => $token['raw']]);
                } else {
                    $weightReturns[] = $resolved;
                }
            } else {
                $errors[] = __(':code: not recognised', ['code' => $token['raw']]);
            }
        }

        return ['ok' => $errors === [], 'loans' => $loans, 'weightReturns' => $weightReturns, 'errors' => $errors];
    }

    private function findOpenLoan(string $type, int $number, array $excludeIds): ?EquipmentLoan
    {
        return EquipmentLoan::whereNull('returned_at')
            ->whereHas('equipment', fn ($q) => $q->where('type', $type))
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->with('equipment')
            ->get()
            ->first(fn (EquipmentLoan $l): bool => $this->numberMatches($l->equipment->short_number, $number));
    }

    private function numberMatches(?string $shortNumber, int $number): bool
    {
        if ($shortNumber === null) {
            return false;
        }

        $digits = preg_replace('/\D/', '', $shortNumber);

        return $digits !== '' && (int) $digits === $number;
    }

    /** @return array{event: Event, quantity: int}|null */
    private function resolveWeightReturn(int $quantity): ?array
    {
        $eventIds = EventWeightLoan::query()
            ->whereColumn('quantity_returned', '<', 'quantity')
            ->distinct()
            ->pluck('event_id');

        if ($eventIds->count() !== 1) {
            return null;
        }

        $event = Event::find($eventIds->first());
        if ($event === null) {
            return null;
        }

        $outstanding = EventWeightLoan::where('event_id', $event->id)
            ->get()
            ->sum(fn (EventWeightLoan $l): int => $l->outstanding());

        if ($quantity > $outstanding) {
            return null;
        }

        return ['event' => $event, 'quantity' => $quantity];
    }

    /**
     * @param  array{ok: bool, loans: list<EquipmentLoan>, weightReturns: list<array{event: Event, quantity: int}>, errors: list<string>}  $preview
     */
    public function apply(array $preview): void
    {
        DB::transaction(function () use ($preview): void {
            /** @var array<int, array{short_number: string|null, name: string}> $byUser */
            $byUser = [];

            foreach ($preview['loans'] as $loan) {
                $loan->update(['returned_at' => now(), 'returned_by' => auth()->id()]);
                $equipment = $loan->equipment;
                $status = $equipment->hasOverdueMaintenance() ? 'maintenance_required' : 'available';
                $equipment->update(['status' => $status, 'last_seen_at' => now()]);

                if ($loan->user_id !== null) {
                    $byUser[$loan->user_id][] = ['short_number' => $equipment->short_number, 'name' => $equipment->name];
                }
            }

            foreach ($preview['weightReturns'] as $weightReturn) {
                $this->consumeWeightReturn($weightReturn['event'], $weightReturn['quantity'], $byUser);
            }

            foreach ($byUser as $userId => $items) {
                SendGearReturnedEmail::dispatch($userId, $items, null);
            }
        });
    }

    /** @param array<int, array<int, array{short_number: string|null, name: string}>> $byUser */
    private function consumeWeightReturn(Event $event, int $quantity, array &$byUser): void
    {
        $remaining = $quantity;
        $rows = EventWeightLoan::where('event_id', $event->id)
            ->whereColumn('quantity_returned', '<', 'quantity')
            ->orderBy('loaned_at')
            ->get();

        foreach ($rows as $row) {
            if ($remaining <= 0) {
                break;
            }

            $consume = min($remaining, $row->outstanding());
            $row->quantity_returned += $consume;
            $row->returned_at = $row->quantity_returned >= $row->quantity ? now() : null;
            $row->save();
            $remaining -= $consume;

            $userId = $row->eventRegistration?->user_id;
            if ($userId !== null) {
                $byUser[$userId][] = ['short_number' => null, 'name' => (string) __(':count weight(s)', ['count' => $consume])];
            }
        }
    }
}
