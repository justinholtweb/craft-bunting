<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Commands;

use CraftCms\Cms\Console\CraftCommand;
use Illuminate\Console\Command;
use JustinHoltWeb\Bunting\Flags\AuditLog;
use JustinHoltWeb\Bunting\Flags\Flags;

/**
 * Drops Pennant's stored values (they're worked out again on the next check).
 * Bunting purges a flag by itself whenever its rollout changes; this is for
 * values set from app code with `Feature::activate()`, or a clean slate.
 */
class PurgeCommand extends Command
{
    use CraftCommand;

    protected $signature = 'craft:bunting:purge {flags?* : Flag handles (default: every flag)}';

    protected $description = 'Drops the stored (resolved) values of flags';

    public function handle(Flags $flags, AuditLog $audit): int
    {
        $handles = array_values(array_map('strval', (array) $this->argument('flags')));

        foreach ($handles as $handle) {
            if ($flags->get($handle) === null) {
                $this->error("Unknown flag “{$handle}”.");

                return self::FAILURE;
            }
        }

        $flags->purgeStored(...$handles);

        foreach ($handles === [] ? array_keys($flags->all()) : $handles as $handle) {
            $audit->record(AuditLog::PURGED, $handle, $flags->get($handle)?->uid);
        }

        $this->info('Purged stored values for '.($handles === [] ? 'every flag' : implode(', ', $handles)).'.');

        return self::SUCCESS;
    }
}
