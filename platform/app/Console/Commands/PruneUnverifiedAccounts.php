<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Deletes accounts that never confirmed their address.
 *
 * Unverified accounts are how a signup form becomes a spam surface: an
 * attacker registers thousands of addresses they do not own, and every one of
 * them keeps a row, a handle reservation and a persona slot forever. Expiring
 * them returns all three.
 *
 * The safety rails matter more than the sweep. An account is only ever removed
 * when it is genuinely inert — nothing written, nothing marked, nothing said,
 * and no social identity, since a social sign-in whose provider did not assert
 * a verified address is still a real person who logged in.
 */
class PruneUnverifiedAccounts extends Command
{
    protected $signature = 'inkfathom:prune-unverified
                            {--days=30 : Grace period before an unconfirmed account expires}
                            {--dry-run : List what would be removed and change nothing}';

    protected $description = 'Remove inert accounts that never confirmed their email address';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $query = User::query()
            ->whereNull('email_verified_at')
            ->where('created_at', '<', $cutoff)
            ->whereDoesntHave('posts')
            ->whereDoesntHave('highlights')
            ->whereDoesntHave('responses')
            ->whereDoesntHave('lettersSent')
            ->whereDoesntHave('socialIdentities');

        if ($this->option('dry-run')) {
            $query->each(function (User $user) {
                $this->line("would remove #{$user->id} {$user->email} (joined {$user->created_at->toDateString()})");
            });

            $this->info("Dry run: {$query->count()} account(s) match.");

            return self::SUCCESS;
        }

        // Chunked by id so a large sweep does not build one enormous delete,
        // and so deleting rows cannot shift the pages underneath the cursor.
        $removed = 0;
        $query->chunkById(200, function ($users) use (&$removed) {
            /** @var Collection<int, User> $users */
            $users->each(function (User $user) use (&$removed) {
                $user->personas()->delete();
                $user->delete();
                $removed++;
            });
        });

        $this->info("Removed {$removed} unconfirmed account(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
