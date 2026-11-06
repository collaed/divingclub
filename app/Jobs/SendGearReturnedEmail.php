<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendGearReturnedEmail implements ShouldQueue
{
    use Dispatchable, Queueable;

    /** @param list<array{short_number: string|null, name: string}> $items */
    public function __construct(public int $userId, public array $items, public ?int $weightQuantity) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        $email = $user?->primary_email;
        if (! $user || ! $email || ($this->items === [] && $this->weightQuantity === null)) {
            return;
        }

        try {
            $html = view('emails.gear-returned', [
                'items' => $this->items,
                'weightQuantity' => $this->weightQuantity,
            ])->render();

            Mail::html($html, fn ($m) => $m->to($email)->subject('Matériel restitué / Equipment returned'));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
