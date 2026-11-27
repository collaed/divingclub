<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * One-off: stars each member's highest-rank cert in a track, but only for
 * members with no star at all in that track yet — members who already
 * starred something keep exactly what they have, even if it isn't the
 * highest-rank cert (that's for a human to review, not to auto-correct).
 */
class BackfillCertificationStars extends Command
{
    protected $signature = 'certifications:backfill-stars';

    protected $description = "Star each member's highest-rank cert per track, for members with no star yet in that track";

    public function handle(): int
    {
        $count = 0;
        User::whereHas('certificationLevels')->with('certificationLevels')->chunkById(100, function ($users) use (&$count): void {
            foreach ($users as $user) {
                $user->backfillMissingCertificationStars();
                $count++;
            }
        });

        $this->info("Processed {$count} members with certifications.");

        return self::SUCCESS;
    }
}
