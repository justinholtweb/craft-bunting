<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Commands;

use CraftCms\Cms\Console\CraftCommand;
use Illuminate\Console\Command;
use JustinHoltWeb\Bunting\Flags\Flags;

/** Flags no code has checked lately — candidates for removal. */
class StaleCommand extends Command
{
    use CraftCommand;

    protected $signature = 'craft:bunting:stale {--days=30 : Not checked in this many days} {--fail : Exit non-zero when any flag is stale (for CI)}';

    protected $description = 'Lists flags that haven’t been checked in a while';

    public function handle(Flags $flags): int
    {
        $days = max(1, (int) $this->option('days'));
        $stale = $flags->stale($days);

        if ($stale === []) {
            $this->info("Every flag was checked in the last {$days} days.");

            return self::SUCCESS;
        }

        $this->table(['Flag', 'Last checked (UTC)'], array_map(fn ($flag) => [$flag->handle, $flag->lastCheckedAt?->format('Y-m-d H:i') ?? 'never'], $stale));

        return $this->option('fail') ? self::FAILURE : self::SUCCESS;
    }
}
