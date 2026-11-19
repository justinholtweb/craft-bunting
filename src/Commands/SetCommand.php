<?php

declare(strict_types=1);

namespace JustinHoltWeb\Bunting\Commands;

use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Cms\Support\Facades\Sites;
use Illuminate\Console\Command;
use JustinHoltWeb\Bunting\Flags\Flag;
use JustinHoltWeb\Bunting\Flags\Flags;

/**
 * Flip a flag from a deploy script or an incident: `craft:bunting:set new-nav off`,
 * `craft:bunting:set new-nav on --site=fr`, `craft:bunting:set new-nav rules --percentage=25`.
 * Audited (as the console).
 */
class SetCommand extends Command
{
    use CraftCommand;

    protected $signature = 'craft:bunting:set {flag : The flag handle} {mode : off, on, rules, or inherit (with --site)} {--site= : Set the mode for one site only} {--percentage= : Rollout percentage (0–100) for “rules” mode}';

    protected $description = 'Sets a flag’s mode (globally or for one site) and rollout percentage';

    public function handle(Flags $flags): int
    {
        $flag = $flags->get((string) $this->argument('flag'));
        $mode = (string) $this->argument('mode');
        $siteHandle = $this->option('site');

        if ($flag === null) {
            $this->error('Unknown flag.');

            return self::FAILURE;
        }

        if (! in_array($mode, [...Flag::MODES, ...($siteHandle !== null ? ['inherit'] : [])], true)) {
            $this->error('The mode must be off, on or rules'.($siteHandle !== null ? ', or inherit' : '').'.');

            return self::FAILURE;
        }

        $rollout = [];

        if ($siteHandle !== null) {
            $site = Sites::getSiteByHandle((string) $siteHandle, true);

            if ($site === null) {
                $this->error('Unknown site.');

                return self::FAILURE;
            }

            $siteModes = $flag->siteModes;

            if ($mode === 'inherit') {
                unset($siteModes[$site->uid]);
            } else {
                $siteModes[(string) $site->uid] = $mode;
            }

            $rollout['siteModes'] = $siteModes;
        } else {
            $rollout['mode'] = $mode;
        }

        if ($this->option('percentage') !== null) {
            $percentage = filter_var($this->option('percentage'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);

            if ($percentage === false) {
                $this->error('The percentage must be a whole number from 0 to 100.');

                return self::FAILURE;
            }

            $rollout['percentage'] = $percentage;
        }

        $changed = $flags->saveRollout($flag, $rollout);
        $this->info($changed ? "Saved “{$flag->handle}”." : 'Nothing changed.');

        return self::SUCCESS;
    }
}
