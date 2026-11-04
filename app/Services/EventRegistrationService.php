<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\PaymentExpected;
use App\Models\ThemeSetting;
use App\Models\TripParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Handles event registration, cancellation, and waiting list promotion.
 *
 * Extracted from EventController to keep registration logic testable
 * and controllers under 300 lines.
 */
class EventRegistrationService
{
    public function __construct(
        private MedicalComplianceService $medicalService,
    ) {}

    /**
     * Register a non-member (bureau only).
     *
     * @return array{success: bool, message: string}
     */
    public function registerNonMember(Event $event, string $name, ?string $comment, User $actor): array
    {
        if ($event->status === 'cancelled') {
            return ['success' => false, 'message' => __('This event is cancelled.')];
        }

        if ($event->registrations()->whereNull('user_id')->where('non_member_name', $name)->whereIn('status', ['confirmed', 'waiting'])->exists()) {
            return ['success' => false, 'message' => __(':name is already registered.', ['name' => $name])];
        }

        EventRegistration::create([
            'event_id' => $event->id,
            'user_id' => null,
            'non_member_name' => $name,
            'status' => 'confirmed',
            'comment' => $comment,
            'registered_by' => $actor->id,
        ]);

        if ($event->hasTripSettlement()) {
            TripParticipant::firstOrCreate(
                ['event_id' => $event->id, 'non_member_name' => $name, 'user_id' => null]
            );
        }

        return ['success' => true, 'message' => __(':who registered successfully.', ['who' => $name])];
    }

    /**
     * Register a member for an event.
     *
     * @return array{success: bool, message: string, warning: ?string}
     */
    public function registerMember(Event $event, User $target, User $actor, ?string $comment, ?string $transitMode): array
    {
        if (! $event->isRegistrationOpen()) {
            return ['success' => false, 'message' => __('Registration is not open for this event.'), 'warning' => null];
        }

        if ($event->registrations()->where('user_id', $target->id)->whereIn('status', ['confirmed', 'waiting', 'pending_licence'])->exists()) {
            return ['success' => false, 'message' => __(':name is already registered.', ['name' => $target->name]), 'warning' => null];
        }

        // Remove old cancelled registration if re-registering
        $event->registrations()->where('user_id', $target->id)->where('status', 'cancelled')->delete();

        $warning = null;

        // Medical compliance gate
        if (in_array($event->event_type, ['pool', 'dive', 'training'])) {
            if (! $target->hasDiveProfile()) {
                $fields = implode(', ', $target->missingDiveProfileFields());
                $msg = $target->id === $actor->id
                    ? __('Please complete your profile before registering: :fields', ['fields' => $fields])
                    : __(':name must complete their profile: :fields', ['name' => $target->name, 'fields' => $fields]);

                return ['success' => false, 'message' => $msg, 'warning' => null];
            }

            if (! $this->medicalService->isCompliant($target, $event->event_date)) {
                $warning = $target->id === $actor->id
                    ? __('Warning: your medical certificate will not be valid on the event date. You can still register, but please update it before the event.')
                    : __('Warning: :name\'s medical certificate will not be valid on the event date.', ['name' => $target->name]);
            }
        }

        // Federation licence gate: held back as "pending_licence" (not confirmed
        // or waitlisted) until an active licence is on file — the bureau can
        // still see the member intends to come and recheck once it's sorted.
        if ($event->required_federation_id && ! $target->hasActiveLicence($event->requiredFederation, $event->event_date)) {
            $registeredBy = $target->id !== $actor->id ? $actor->id : null;

            EventRegistration::create([
                'event_id' => $event->id,
                'user_id' => $target->id,
                'status' => 'pending_licence',
                'comment' => $comment,
                'transit_mode' => $transitMode,
                'registered_by' => $registeredBy,
            ]);

            $message = $target->id === $actor->id
                ? __('Registered, but held pending an active :federation licence — it will not be confirmed until one is on file.', ['federation' => $event->requiredFederation->acronym])
                : __(':name was registered, but held pending an active :federation licence.', ['name' => $target->name, 'federation' => $event->requiredFederation->acronym]);

            return ['success' => true, 'message' => $message, 'warning' => $warning];
        }

        // Check capacity
        if ($event->isFull() && ! $event->waiting_list_enabled) {
            return ['success' => false, 'message' => __('Event is full.'), 'warning' => null];
        }

        DB::transaction(function () use ($event, $target, $actor, $comment, $transitMode): void {
            $registeredBy = $target->id !== $actor->id ? $actor->id : null;
            $this->finalizeRegistration($event, $target, $registeredBy, $comment, $transitMode);
        });

        $who = $target->id !== $actor->id ? $target->name : __('You');

        return ['success' => true, 'message' => __(':who registered successfully.', ['who' => $who]), 'warning' => $warning];
    }

    /**
     * Re-run the federation-licence check for a held `pending_licence`
     * registration and, if it now passes, finalize it as confirmed/waiting.
     *
     * @return array{success: bool, message: string}
     */
    public function recheckLicence(EventRegistration $reg): array
    {
        $event = $reg->event;

        if ($reg->status !== 'pending_licence' || ! $event->required_federation_id) {
            return ['success' => false, 'message' => __('Nothing to recheck.')];
        }

        if (! $reg->user->hasActiveLicence($event->requiredFederation, $event->event_date)) {
            return ['success' => false, 'message' => __('Still no active :federation licence on file.', ['federation' => $event->requiredFederation->acronym])];
        }

        if ($event->isFull() && ! $event->waiting_list_enabled) {
            return ['success' => false, 'message' => __('Event is full.')];
        }

        DB::transaction(function () use ($reg, $event): void {
            $target = $reg->user;
            $registeredBy = $reg->registered_by !== null ? (int) $reg->registered_by : null;
            $comment = $reg->comment;
            $transitMode = $reg->transit_mode;

            $reg->delete();
            $this->finalizeRegistration($event, $target, $registeredBy, $comment, $transitMode);
        });

        return ['success' => true, 'message' => __('Registration confirmed.')];
    }

    /** Create the confirmed/waiting registration row, deposit payment, and trip participant link. */
    private function finalizeRegistration(Event $event, User $target, ?int $registeredBy, ?string $comment, ?string $transitMode): void
    {
        if ($event->isFull()) {
            $pos = ($event->waitingRegistrations()->max('waiting_list_position') ?? 0) + 1;
            EventRegistration::create([
                'event_id' => $event->id,
                'user_id' => $target->id,
                'status' => 'waiting',
                'waiting_list_position' => $pos,
                'comment' => $comment,
                'transit_mode' => $transitMode,
                'registered_by' => $registeredBy,
            ]);
        } else {
            EventRegistration::create([
                'event_id' => $event->id,
                'user_id' => $target->id,
                'status' => 'confirmed',
                'comment' => $comment,
                'transit_mode' => $transitMode,
                'registered_by' => $registeredBy,
            ]);

            $this->generateDepositPayment($event, $target);
        }

        if ($event->hasTripSettlement()) {
            TripParticipant::firstOrCreate(
                ['event_id' => $event->id, 'user_id' => $target->id]
            );
        }
    }

    /**
     * Cancel a registration and handle waiting list promotion.
     *
     * @return array{success: bool, message: string}
     */
    public function cancel(Event $event, EventRegistration $reg, User $actor, ?string $cancelComment): array
    {
        $wasConfirmed = $reg->status === 'confirmed';

        $reg->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $actor->id,
            'cancel_comment' => $cancelComment,
        ]);

        // Handle payments
        if ($reg->user_id) {
            $paidPayment = PaymentExpected::where('event_id', $event->id)
                ->where('user_id', $reg->user_id)
                ->where('status', 'paid')
                ->exists();

            PaymentExpected::where('event_id', $event->id)
                ->where('user_id', $reg->user_id)
                ->where('status', 'pending')
                ->delete();

            if ($paidPayment) {
                PaymentExpected::where('event_id', $event->id)
                    ->where('user_id', $reg->user_id)
                    ->where('status', 'paid')
                    ->update(['refund_review_needed' => true]);
            }
        }

        // Auto-promote
        if ($wasConfirmed) {
            $next = $event->waitingRegistrations()->first();
            if ($next) {
                $next->update(['status' => 'confirmed', 'waiting_list_position' => null]);
            }
        }

        // Remove trip participant
        if ($event->hasTripSettlement()) {
            if ($reg->user_id) {
                TripParticipant::where('event_id', $event->id)->where('user_id', $reg->user_id)->delete();
            } elseif ($reg->non_member_name) {
                TripParticipant::where('event_id', $event->id)->where('non_member_name', $reg->non_member_name)->delete();
            }
        }

        return ['success' => true, 'message' => __('Registration cancelled.')];
    }

    /** Generate deposit payment if the event has deposits configured. */
    private function generateDepositPayment(Event $event, User $target): void
    {
        $totalDue = 0;
        $components = [];
        foreach ([1, 2, 3] as $i) {
            $amt = $event->{"deposit_{$i}_amount"};
            if ($amt > 0) {
                $totalDue += $amt;
                $components[] = [
                    'label' => __('Deposit')." $i".($event->{"deposit_{$i}_date"} ? ' ('.$event->{"deposit_{$i}_date"}->format('d/m/Y').')' : ''),
                    'amount' => (float) $amt,
                ];
            }
        }

        if ($totalDue > 0) {
            $name = strtoupper($target->detail?->last_name ?? 'MEMBER');
            PaymentExpected::create([
                'user_id' => $target->id,
                'type' => 'event',
                'event_id' => $event->id,
                'season_year' => $event->event_date->format('Y'),
                'amount_due' => $totalDue,
                'communication' => ThemeSetting::get('club_short_code', config('club.id', 'CLUB')).'-'.$event->event_date->format('Y').'-'.$event->id.'-'.$name,
                'components' => $components,
                'status' => 'pending',
            ]);
        }
    }
}
