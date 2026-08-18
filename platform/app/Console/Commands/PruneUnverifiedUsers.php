<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Remove abandoned signups — unverified, past the grace window, and empty.
 *
 * The eligibility rule lives in User::scopeAbandonedUnverified() and is
 * deliberately conservative: anything that looks like a real person, however
 * faintly, is left alone. A cleanup job that deletes a writer's drafts is a
 * far worse outcome than a table with some dead rows in it.
 */
class PruneUnverifiedUsers extends Command
{
    protected $signature = 'users:prune-unverified
                            {--days= : Override the grace period in days}
                            {--dry-run : List what would be deleted, delete nothing}';

    protected $description = 'Delete unverified, empty accounts past the grace period';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('auth.unverified_grace_days'));

        if ($days < 1) {
            $this->error('The grace period must be at least one day.');

            return self::FAILURE;
        }

        $query = User::query()->abandonedUnverified($days);
        $count = $query->count();

        if ($count === 0) {
            $this->info("No abandoned unverified accounts older than {$days} days.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(
                ['id', 'email', 'registered'],
                $query->get(['id', 'email', 'created_at'])
                    ->map(fn (User $user) => [$user->id, $user->email, $user->created_at->toDateString()])
                    ->all(),
            );
            $this->info("{$count} account(s) would be deleted.");

            return self::SUCCESS;
        }

        // Chunked by id so the delete does not load every row at once and does
        // not walk a result set it is concurrently shrinking.
        $deleted = 0;
        $query->chunkById(200, function ($users) use (&$deleted) {
            foreach ($users as $user) {
                $user->delete();
                $deleted++;
            }
        });

        $this->info("Deleted {$deleted} abandoned unverified account(s).");

        return self::SUCCESS;
    }
}
