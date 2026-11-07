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

    /** BCD size prefixes a code may optionally start with (longest first, so "xxs" isn't cut short at "xs"). */
    private const BCD_SIZES = ['xxxs', 'xxs', 'xxl', 'xs', 'xl', 's', 'm', 'l'];

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
                $code = $token['code'];
                $size = null;
                if ($type === 'bcd') {
                    [$size, $code] = $this->splitBcdSize($code);
                }
                $loan = $this->findOpenLoan($type, $code, $size, $claimedLoanIds);
                if ($loan === null) {
                    $errors[] = __(':code: no :type matching :value is currently on loan', ['code' => $token['raw'], 'type' => $type, 'value' => $token['code']]);
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

    private function findOpenLoan(string $type, string $code, ?string $size, array $excludeIds): ?EquipmentLoan
    {
        return EquipmentLoan::whereNull('returned_at')
            ->whereHas('equipment', function ($q) use ($type, $size) {
                $q->where('type', $type);
                if ($size !== null) {
                    $q->whereRaw('LOWER(size) = ?', [$size]);
                }
            })
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->with('equipment')
            ->get()
            ->first(fn (EquipmentLoan $l): bool => $this->codeMatches($l->equipment->short_number, $code));
    }

    private function codeMatches(?string $shortNumber, string $code): bool
    {
        return $shortNumber !== null && strcasecmp(trim($shortNumber), $code) === 0;
    }

    /**
     * Splits an optional leading size prefix off a BCD code, e.g. "xs36" => ['xs', '36'].
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
